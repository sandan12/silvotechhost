# HOSTiQ deployment

This branch builds a static Next.js export for the cPanel document root `/home/finespir/silvotech.eu`.
The enquiry form posts to `/send-form.php`, which delivers locally to `sales@silvotech.eu` with optional JPG, PNG, WEBP or PDF attachments (4 MB each).

Build with GitHub Actions (`Build HOSTiQ upload package`) and download the `silvotech-hostiq-upload` artifact. Upload the contents of `silvotech-hostiq-upload.zip` to the domain document root, preserving the existing `.well-known` directory. Test all locales and the form before deleting the old site backup.
