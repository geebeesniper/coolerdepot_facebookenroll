-- v0.1.71 website reference library
-- The PHP migration script adds this column conditionally for compatibility.
ALTER TABLE cdsp_website_references
    ADD COLUMN description MEDIUMTEXT NULL AFTER title;
