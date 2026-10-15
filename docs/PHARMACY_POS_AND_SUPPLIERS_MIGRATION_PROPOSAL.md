# Pharmacy POS and supplier migration proposal

The current schema has medicines, inventory batches, stock movements,
prescriptions, invoices, invoice items, and payments. It has no sale header,
sale lines, supplier, receipt, or idempotency data model. A POS checkout cannot
be implemented safely without adding those records.

Proposed migration set (not created or applied):

1. `suppliers`: unique code, name, contact details, address, active flag, and
   timestamps.
2. `stock_receipts`: unique receipt number, nullable supplier, received date,
   receiving user, external reference, status, notes, and timestamps.
3. `stock_receipt_lines`: receipt, medicine, batch number, expiry date,
   quantity, unit cost, location, and the created inventory batch. Add a unique
   receipt/batch/medicine constraint so retried receipts cannot create stock
   twice.
4. `pharmacy_sales`: unique sale number, nullable patient and prescription,
   linked invoice, status (`completed`, `voided`, `refunded`), idempotency key,
   cashier, timestamps, and void/refund audit fields. The idempotency key must
   be unique.
5. `pharmacy_sale_lines`: sale, medicine, optional prescription item,
   server-calculated unit price, discount, quantity, line total, and stock
   movement references. Restrict prescription-only sale lines to a valid
   prescription.

The checkout transaction would lock eligible FEFO batches through
`InventoryService`, create the invoice and invoice items through the existing
billing workflow, record payment where requested, then persist a completed sale
and its stock movements atomically. Voids/refunds would use reversing
movements—never delete a completed sale.

Before approval, clinic policy must also define which medicines are
prescription-only and whether walk-in sales are allowed. Neither attribute
exists in the current medicine schema.
