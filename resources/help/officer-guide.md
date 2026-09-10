# QSU-FMAS Officer Guide

This is the reference the in-app help assistant answers from. Keep it in plain
language, task-first ("How do I ..."), and update it whenever the UI changes.
Anything not written here, the assistant will not answer.

---

## What this system is

QSU-FMAS is the Student Government's file management and archiving system. Officers
use it to store folders and files by school year, turn scanned hardcopies into
searchable documents, publish announcements, and archive a school year's records
when the term ends. Officers serve a one-year term, so this guide exists to get
each new batch productive quickly.

## Roles

- **Officer / Admin** – day-to-day work: folders, files, documents, scan inbox,
  archives, trash, templates, reports, announcements, site content.
- **Super Admin** – everything an officer can do, plus Manage Admins, Activity
  Logs, and system Settings (backups). Some restricted folders are only visible
  to their owner and the Super Admin.

## Signing in

Go to the login page and use the email and password given to you by the Super
Admin. On first login you may be asked to change your password. Forgot it? Use
"Forgot password" on the login page to get a reset link by email.

---

## Dashboard

The landing page after login. Shows totals (folders, files, storage used) and
your most recent folders. Use the quick links to jump to Folders, Reports, or
Archives.

## Folders

The main store for files, grouped by school year.

- **Open a folder:** click it, or use the three-dot menu → Open.
- **Create a folder:** click the green **+** button (bottom-right) → New folder,
  type a name, Create.
- **Rename:** three-dot menu → Rename.
- **Delete:** three-dot menu → Delete. It goes to Trash, not gone permanently.
- **Search:** the search bar at the top searches files and folders; matches
  appear in a dropdown as you type.
- **Restricted folders:** a folder with a lock icon is limited to specific
  officers. Only the owner or a Super Admin can change who has access
  (three-dot menu → Manage access).

### Manage access (restricting a folder)

Three-dot menu → Manage access → turn on "Restrict this folder to specific
officers", then tick the officers who should also see it. You and the Super Admin
always keep access.

### Start New School Year

On the Folders page, "Start New School Year" stores every folder and file
currently on the page under the school year you enter, then clears the page so
you start the new term empty. The stored year stays reachable from "Previous
School Years". This does not delete anything.

## Uploading files

Open the folder you want, use the upload option, then either click to browse or
drag files onto the drop zone. Uploads go straight to cloud storage and show
progress in a small toast in the top-right corner; you can keep working while
they finish. If a direct upload fails it automatically retries through the
server. Accepted types include PDF, Word, Excel, PowerPoint, and common images.

## Documents

Documents are files that have been through text extraction (OCR) so their
contents are searchable, not just their name. A file uploaded with the
"scan as document" option, or anything processed from the Scan Inbox, becomes a
Document. Use the Documents page to search inside document text and open the
reader view.

## Scan Inbox (Digitize Hardcopy)

Turns scanned paper into Documents.

- The office scanner drops finished PDFs into a watched folder; the system picks
  them up automatically on a schedule.
- One physical stack can hold several documents – separate them with a barcode
  separator sheet (or a blank page) and each part becomes its own document.
- New scans appear in the Scan Inbox for review. Open one, check the detected
  title and page split, fix anything wrong, then approve it to file it as a
  Document. Reject a bad scan to discard it.

## Archives

A finished school year, zipped and stored for the record.

- **Archive one folder:** on Folders, three-dot menu → Archive.
- **Browse an archive:** Archives page → open it to see the files inside, and
  preview individual files without downloading.
- **Restore:** an archive can be restored back into active folders from its menu.
- **Integrity:** archives are checksummed. If a checksum no longer matches you
  will see an integrity warning – tell the Super Admin.

## Trash

A recycle bin for deleted files, folders, and documents. Anyone with access can
restore an item. Permanent deletion is Super Admin only. Items may be cleared
automatically after a retention period.

## Document Templates & Letterheads

Reusable starting points for official documents (e.g. a resolution or a memo).
Pick a template, fill in the fields, and generate a PDF. Letterheads are the
header/footer images applied to generated documents – manage them from the
Letterheads area.

## Reports & Analytics

Read-only charts and counts: files and folders over time, storage, activity.
Use it for accomplishment reports at the end of a term.

## Announcements

Public notices shown on the student-facing dashboard. Create one with a title,
body, and optional attachment; it becomes visible to students immediately. Edit
or delete from the same page.

## Site Content

Controls the text and images on the public landing / student pages (mission,
vision, contact details, banner images). Changes show on the public site right
away.

## Profile

Your name, photo, and password. Update your photo or change your password here.

## Notifications

The bell in the top bar. It shows things that need attention – a scan ready for
review, an archive finished, an integrity warning. Click an item to go to it,
or "Mark all read".

## Dark mode

The sun/moon button in the top bar switches between light and dark. Your choice
is remembered on that browser; new officers start on whatever their computer's
system theme is.

---

## Super Admin only

### Manage Admins

Add a new officer (name, email, position – they get a temporary password to
change on first login), edit an officer, or archive one who has finished their
term. Archived officers can be reactivated or permanently removed.

### Activity Logs

A timeline of who did what – uploads, deletions, renames, logins, access
changes. Use it to trace a change or for audit questions.

### Settings / Backup

Create a database backup on demand, download past backups, and set an automatic
backup frequency. Keep automatic backups on.

---

## Common questions

- **"I can't see a folder another officer mentioned."** It is probably
  restricted. Ask its owner or the Super Admin to add you via Manage access.
- **"I deleted something by mistake."** Check Trash and restore it.
- **"A scan came out as one document but it's really three."** Re-scan with a
  separator sheet between each document, or split it during review if the option
  is offered.
- **"How do I start fresh for the new school year?"** Folders page → Start New
  School Year. It archives the current page's contents under the old year first.
- **"Upload is stuck / failed."** It retries through the server automatically;
  if it still fails, check your connection and file type, then try again.


