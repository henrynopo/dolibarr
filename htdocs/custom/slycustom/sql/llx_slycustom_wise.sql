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

-- Wise outgoing transfers mapping (flow A). A transfer is created UNFUNDED;
-- funding manually in the Wise dashboard is the approval gate.
create table if not exists llx_slycustom_wise_transfer
(
  rowid                   integer AUTO_INCREMENT PRIMARY KEY,
  entity                  integer       NOT NULL DEFAULT 1,
  fk_facture_fourn        integer       NOT NULL,               -- supplier invoice
  fk_user_creat           integer       NULL,
  wise_quote_id           varchar(64)   DEFAULT '',
  wise_recipient_id       bigint        NULL,
  wise_transfer_id        bigint        NULL,
  customer_transaction_id varchar(36)   DEFAULT '',              -- Wise idempotency uuid
  source_currency         varchar(3)    DEFAULT '',
  target_currency         varchar(3)    DEFAULT '',
  target_amount           double(24,8)  DEFAULT 0,              -- what the vendor receives
  source_amount           double(24,8)  DEFAULT 0,
  rate                    double(24,12) DEFAULT 0,
  fee                     double(24,8)  DEFAULT 0,
  reference_sent          varchar(100)  DEFAULT '',             -- vendor order reference sent
  reference_source        varchar(32)   DEFAULT '',             -- order_ref_supplier|invoice_ref_supplier|our_ref
  status                  varchar(24)   NOT NULL DEFAULT 'DRAFT', -- DRAFT|SENT|RECORDED|CANCELLED|ERROR
  last_state              varchar(64)   DEFAULT '',             -- last Wise state seen
  last_event_at           datetime      NULL,                   -- UTC, ordering guard
  fk_paiement_fourn       integer       NULL,
  note                    text,
  date_creation           datetime,
  tms                     timestamp
) ENGINE=innodb;
