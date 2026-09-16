# Enabling Real PDF Generation (DOMPDF)

The Document Center (Admin → Document Center) can save, share, and manage
routine documents as **draft** or **final** versions with permanent share
links. To generate true, downloadable `.pdf` files (instead of styled HTML),
DOMPDF needs to be installed once via Composer.

## One-time setup (run on your live server, via SSH or your host's terminal)

```bash
cd /path/to/your/project/root   # the folder containing composer.json
composer require dompdf/dompdf
```

That's it — no code changes needed. The system automatically detects
`vendor/autoload.php` and switches from HTML fallback to real PDF output.

## If you don't have SSH/terminal access

Some shared hosts don't offer Composer/SSH access. In that case:

1. On your own computer (with PHP + Composer installed), create an empty
   folder, copy `composer.json` from this project into it, and run
   `composer install`.
2. Upload the resulting `vendor/` folder into your project's root directory
   via FTP/File Manager (same level as `admin/`, `public/`, `config/`).

## What works before DOMPDF is installed

Everything — the Document Center still works fully:
- Generate, save, and share documents (as styled HTML instead of PDF)
- Draft/Final status, watermarks, regenerate, delete — all functional
- Once DOMPDF is installed, click **"Regenerate"** on any existing document
  to convert it into a real PDF — no need to re-create anything.

## Where documents are stored

Generated files live in `assets/uploads/documents/`. Each has a permanent,
unguessable share link shown via the "Share" button in the Document Center —
safe to send to teachers/students without giving them admin access.
