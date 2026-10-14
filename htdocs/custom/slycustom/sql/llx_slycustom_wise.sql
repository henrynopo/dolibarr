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
-- 2.5.0 slim-down: the previous llx_slycustom_wise_event and
-- llx_slycustom_wise_incoming tables are dropped. Webhook deliveries and
-- email-collector messages now live as JSON files under
-- DOL_DATA_ROOT/wise_webhook/accepted/ with a per-dump sidecar
-- <basename>.meta.json carrying the status / source / note fields.
-- Re-deliveries dedupe on filename (md5[0:8] of the raw body); there's
-- no need for a payload_md5 column.
--
-- Notes:
--  - occurred_at / sent_at are stored in UTC (Wise sends ISO 8601 Z).
-- ===================================================================

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
