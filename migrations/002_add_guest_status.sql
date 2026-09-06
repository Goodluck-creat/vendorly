-- Migration: 002_add_guest_status.sql
-- Adds 'guest' as a valid users.status value, enabling guest checkout/chat
-- without requiring a password or full registration.
--
-- A guest buyer is a real row in `users` (role='customer', status='guest') with an
-- unusable random password_hash. Every table that already references users.id —
-- orders, messages, reviews — needs NO structural change: a guest's order and chat
-- history live in exactly the same place a registered customer's would. When they
-- later set a real password, we flip status to 'active' on the SAME row — their
-- history isn't migrated anywhere, because it was never anywhere else.

ALTER TABLE users
  MODIFY COLUMN status ENUM('active','pending','suspended','guest') NOT NULL DEFAULT 'active';
