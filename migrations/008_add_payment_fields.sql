-- Migration: 008_add_payment_fields.sql
-- Orders now track real payment: a Paystack reference, payment status, and
-- when it was paid. An order only becomes pending_acceptance once payment is
-- confirmed via Paystack's callback + server-side verification — never just
-- because the customer clicked a button.

ALTER TABLE orders
  ADD COLUMN payment_reference VARCHAR(100) NULL UNIQUE AFTER agreed_price,
  ADD COLUMN payment_status ENUM('unpaid','paid','failed') NOT NULL DEFAULT 'unpaid' AFTER payment_reference,
  ADD COLUMN paid_at TIMESTAMP NULL AFTER payment_status;
