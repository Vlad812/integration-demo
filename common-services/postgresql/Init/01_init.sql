-- Integration service schema (v1)
-- Orders, transactional outbox (Messenger: messenger_messages), trades cache

CREATE EXTENSION IF NOT EXISTS pgcrypto;

-- ---------------------------------------------------------------------------
-- Enums
-- ---------------------------------------------------------------------------

CREATE TYPE order_direction AS ENUM ('BUY', 'SELL');
CREATE TYPE order_type AS ENUM ('MARKET');
CREATE TYPE order_status AS ENUM (
    'NEW',              -- принят HTTP-слоем, ещё не отправлен брокеру
    'RETRYING',         -- временная ошибка при отправке, ждёт retry
    'SENT_TO_BROKER',   -- успешно принят API брокера
    'PENDING_ROUTING',  -- брокер маршрутизирует заявку на биржу
    'PARTIALLY_FILLED', -- частичное исполнение (polling)
    'FILLED',           -- полностью исполнен
    'FAILED',           -- фатальная ошибка
    'REJECTED'          -- отклонён брокером
);

CREATE TYPE asset_type AS ENUM ('STOCK', 'BOND', 'ETF', 'OTHER');

-- ---------------------------------------------------------------------------
-- orders — заявки на покупку/продажу (state machine + идемпотентность)
-- ---------------------------------------------------------------------------

CREATE TABLE orders (
    id                      UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    idempotency_key         UUID NOT NULL,
    broker_order_id         VARCHAR(64) UNIQUE,
    client_id               VARCHAR(64) NOT NULL,
    ticker                  VARCHAR(32) NOT NULL,
    direction               order_direction NOT NULL,
    order_type              order_type NOT NULL DEFAULT 'MARKET',
    currency                CHAR(3) NOT NULL DEFAULT 'USD',
    requested_quantity      INTEGER NOT NULL CHECK (requested_quantity > 0),
    executed_quantity       INTEGER NOT NULL DEFAULT 0 CHECK (executed_quantity >= 0),
    avg_price_cents         BIGINT CHECK (avg_price_cents IS NULL OR avg_price_cents >= 0),
    total_value_cents       BIGINT CHECK (total_value_cents IS NULL OR total_value_cents >= 0),
    expected_commission_cents BIGINT CHECK (expected_commission_cents IS NULL OR expected_commission_cents >= 0),
    status                  order_status NOT NULL DEFAULT 'NEW',
    broker_status           VARCHAR(64),
    idempotency_response    JSONB,
    created_at              TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at              TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    broker_created_at       TIMESTAMPTZ,
    broker_updated_at       TIMESTAMPTZ,
    last_polled_at          TIMESTAMPTZ,
    CONSTRAINT chk_orders_executed_qty
        CHECK (executed_quantity <= requested_quantity)
);

COMMENT ON TABLE orders IS 'Локальный кэш заявок: идемпотентность, state machine, polling статусов';
COMMENT ON COLUMN orders.idempotency_key IS 'UUID от фронтенда (Idempotency-Key), уникален в паре с client_id';
COMMENT ON COLUMN orders.broker_order_id IS 'Внешний ID заявки у партнёра-брокера (ord_...)';
COMMENT ON COLUMN orders.idempotency_response IS 'Сохранённый HTTP-ответ для повторных запросов с тем же ключом';

CREATE UNIQUE INDEX uq_orders_client_idempotency_key ON orders (client_id, idempotency_key);
CREATE INDEX idx_orders_client_id ON orders (client_id);
CREATE INDEX idx_orders_broker_order_id ON orders (broker_order_id) WHERE broker_order_id IS NOT NULL;
CREATE INDEX idx_orders_status ON orders (status);
CREATE INDEX idx_orders_polling ON orders (last_polled_at NULLS FIRST)
    WHERE status IN ('SENT_TO_BROKER', 'PENDING_ROUTING', 'PARTIALLY_FILLED', 'RETRYING');

-- ---------------------------------------------------------------------------
-- messenger_messages — transactional outbox (Symfony Doctrine transport)
-- ---------------------------------------------------------------------------

CREATE TABLE messenger_messages (
    id              BIGSERIAL PRIMARY KEY,
    body            TEXT NOT NULL,
    headers         TEXT NOT NULL,
    queue_name      VARCHAR(190) NOT NULL,
    created_at      TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    available_at    TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    delivered_at    TIMESTAMP(0) WITHOUT TIME ZONE
);

COMMENT ON TABLE messenger_messages IS 'Doctrine Messenger: queue_name=outbox (relay) и queue_name=failed (DLQ)';

CREATE INDEX idx_messenger_messages_queue_name ON messenger_messages (queue_name);
CREATE INDEX idx_messenger_messages_available_at ON messenger_messages (available_at);
CREATE INDEX idx_messenger_messages_delivered_at ON messenger_messages (delivered_at);

-- ---------------------------------------------------------------------------
-- trades — локальный кэш истории сделок (cursor-based pagination proxy)
-- ---------------------------------------------------------------------------

CREATE TABLE trades (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    broker_trade_id VARCHAR(64) NOT NULL,
    order_id        UUID REFERENCES orders (id) ON DELETE SET NULL,
    client_id       VARCHAR(64) NOT NULL,
    ticker          VARCHAR(32) NOT NULL,
    direction       order_direction NOT NULL,
    quantity        INTEGER NOT NULL CHECK (quantity > 0),
    price_cents     BIGINT NOT NULL CHECK (price_cents >= 0),
    executed_at     TIMESTAMPTZ NOT NULL,
    synced_at       TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

COMMENT ON TABLE trades IS 'Кэш исполненных сделок с брокера для GET /investments/trades';

CREATE UNIQUE INDEX uq_trades_broker_trade_id ON trades (broker_trade_id);
CREATE INDEX idx_trades_client_executed_at ON trades (client_id, executed_at DESC);
CREATE INDEX idx_trades_order_id ON trades (order_id) WHERE order_id IS NOT NULL;

-- ---------------------------------------------------------------------------
-- portfolio_snapshots + portfolio_positions — опциональный кэш портфеля
-- (GET /investments/portfolio может проксировать брокера или читать snapshot)
-- ---------------------------------------------------------------------------

CREATE TABLE portfolio_snapshots (
    id                  UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    client_id           VARCHAR(64) NOT NULL,
    total_balance_cents BIGINT NOT NULL CHECK (total_balance_cents >= 0),
    cash_cents          BIGINT NOT NULL CHECK (cash_cents >= 0),
    fetched_at          TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

COMMENT ON TABLE portfolio_snapshots IS 'Снимок портфеля клиента с брокера';

CREATE INDEX idx_portfolio_snapshots_client_fetched ON portfolio_snapshots (client_id, fetched_at DESC);

CREATE TABLE portfolio_positions (
    id                  UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    snapshot_id         UUID NOT NULL REFERENCES portfolio_snapshots (id) ON DELETE CASCADE,
    ticker              VARCHAR(32) NOT NULL,
    asset_type          asset_type NOT NULL DEFAULT 'STOCK',
    quantity            INTEGER NOT NULL CHECK (quantity >= 0),
    avg_price_cents     BIGINT NOT NULL CHECK (avg_price_cents >= 0),
    current_price_cents BIGINT NOT NULL CHECK (current_price_cents >= 0)
);

COMMENT ON TABLE portfolio_positions IS 'Позиции внутри snapshot портфеля';

CREATE INDEX idx_portfolio_positions_snapshot_id ON portfolio_positions (snapshot_id);

-- ---------------------------------------------------------------------------
-- updated_at trigger for orders
-- ---------------------------------------------------------------------------

CREATE OR REPLACE FUNCTION set_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_orders_updated_at
    BEFORE UPDATE ON orders
    FOR EACH ROW
    EXECUTE FUNCTION set_updated_at();
