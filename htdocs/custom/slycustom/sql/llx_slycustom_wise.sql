-- ===================================================================
-- SLY Custom - Wise integration tables
--
-- Apply once with the Dolibarr DB user, e.g. from repository root:
--   mysql -u <user> -p <dolibarr_db> < htdocs/custom/slycustom/sql/llx_slycustom_wise.sql
--
-- The same statements are also part of modSlyCustom::init() and are
-- idempotent (CREATE TABLE IF NOT EXISTS), so a module re-activation
-- is an alternative way to install them.
--
-- Notes:
--  - occurred_at / sent_at are stored in UTC (Wise sends ISO 8601 Z).
--  - payload_md5 dedupes re-deliveries: Wise retries on non-2xx and may
--    deliver the same event body more than once. balances#credit (v2.0.0)
--    carries no transaction id, so the raw body hash is the dedupe key.
-- ===================================================================

create table if not exists llx_slycustom_wise_event
(
  rowid             integer AUTO_INCREMENT PRIMARY KEY,
  entity            integer       NOT NULL DEFAULT 1,
  event_type        varchar(64)   NOT NULL DEFAULT '',
  subscription_id   varchar(64)   NOT NULL DEFAULT '',
  schema_version    varchar(16)   NOT NULL DEFAULT '',
  delivery_id       varchar(64)   NOT NULL DEFAULT '',   -- X-Delivery-Id header
  is_test           tinyint       DEFAULT 0,             -- X-Test-Notification: true or zero subscription_id
  occurred_at       datetime      NULL,                  -- UTC, from data.occurred_at (ordering field per docs)
  sent_at           datetime      NULL,                  -- UTC, from envelope sent_at (diagnostic only)
  payload_md5       varchar(32)   NOT NULL DEFAULT '',
  payload_json      mediumtext,
  processed         tinyint       DEFAULT 0,
  processing_note   varchar(255)  DEFAULT '',
  date_creation     datetime,
  unique key uk_payload (payload_md5)
) ENGINE=innodb;

create table if not exists llx_slycustom_wise_incoming
(
  rowid              integer AUTO_INCREMENT PRIMARY KEY,
  entity             integer       NOT NULL DEFAULT 1,
  fk_event           integer       NULL,                 -- llx_slycustom_wise_event.rowid (NULL for cron-sourced rows)
  wise_balance_id    bigint        NULL,                 -- resource.id of the credited balance (0/NULL in test events)
  currency           varchar(3)    NOT NULL DEFAULT '',
  amount             double(24,8)  DEFAULT 0,            -- credited amount, in payment currency
  occurred_at        datetime      NULL,                 -- UTC
  post_balance       double(24,8)  DEFAULT NULL,         -- post_transaction_balance_amount
  status             varchar(24)   NOT NULL DEFAULT 'NEW',
  ref_text           varchar(255)  DEFAULT '',           -- payment reference from statement (usually the SO number)
  counterparty       varchar(255)  DEFAULT '',           -- sender name from statement, when available
  fees               double(24,8)  DEFAULT NULL,
  match_data         text,                              -- JSON snapshot of computed invoice candidates
  fk_soc             integer       NULL,
  fk_paiement        integer       NULL,
  statement_txn_json mediumtext,                         -- raw matched statement transaction (audit)
  note_private       text,
  date_creation      datetime,
  tms                timestamp,
  unique key uk_event (fk_event)
) ENGINE=innodb;
