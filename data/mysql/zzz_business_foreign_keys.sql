-- Fremdschluessel des Business-Moduls. Liegt bewusst in einer "zzz_"-Datei,
-- damit alle Tabellen beim Initialisieren (alphabetisch) schon existieren.
ALTER TABLE `business_offers`
    ADD CONSTRAINT `fk_offer_customer` FOREIGN KEY (`customer_id`) REFERENCES `business_customers` (`customer_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE `business_offer_items`
    ADD CONSTRAINT `fk_offer_item_offer` FOREIGN KEY (`offer_id`) REFERENCES `business_offers` (`offer_id`) ON DELETE CASCADE ON UPDATE RESTRICT;

ALTER TABLE `business_invoices`
    ADD CONSTRAINT `fk_invoice_customer` FOREIGN KEY (`customer_id`) REFERENCES `business_customers` (`customer_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE `business_invoices`
    ADD CONSTRAINT `fk_invoice_offer` FOREIGN KEY (`offer_id`) REFERENCES `business_offers` (`offer_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE `business_invoices`
    ADD CONSTRAINT `fk_invoice_deposit` FOREIGN KEY (`deposit_invoice_id`) REFERENCES `business_invoices` (`invoice_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE `business_invoices`
    ADD CONSTRAINT `fk_invoice_cancels` FOREIGN KEY (`cancels_invoice_id`) REFERENCES `business_invoices` (`invoice_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE `business_invoice_items`
    ADD CONSTRAINT `fk_invoice_item_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `business_invoices` (`invoice_id`) ON DELETE CASCADE ON UPDATE RESTRICT;
