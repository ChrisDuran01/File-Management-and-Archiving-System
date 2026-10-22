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

## Phone app (QSU SG Officers)

A simpler version of the system made for a phone. It is for officers only and
is separate from the students' app.

- **Open it:** there is one login page for the website and the app. Sign in on
  a phone with your usual account and you go straight to the app; on a computer
  you go to the website. Going to `/app` on a computer only shows a note to use
  the website.
- **Install it:** on the app's Home screen tap "Install". On an iPhone, tap the
  Share button, then "Add to Home Screen". Installing and offline files need the
  system to be on a secure (https) address.
- **What you can do in it:** browse folders, open and read files (PDF, Word,
  pictures), search (including inside archives), add a file, and see your
  notifications. Everything else – managing officers, reports, backups,
  archiving, trash – is done on the website.
- **Add a file from the phone:** open a folder → "Add file" → "Take a photo" of
  a paper document or "Choose a file". "Scan as document" turns a photo into a
  searchable PDF. Uploading needs a connection.
- **Offline files:** the app keeps copies of files on the phone so they open
  with no connection, on the "Offline" tab. Files in use this school year are
  saved automatically while the app is open (on Wi-Fi only where the phone can
  tell; an iPhone asks once). Files in restricted folders, from previous school
  years, or larger than 25 MB are not automatic: open the file and tap
  "Keep offline". With no connection, the app shows only the Offline tab.
- **Send a file to a nearby phone with no internet:** the file must be saved on
  the phone. Open it from the Offline tab → "Send or open in another app" → pick
  **Bluetooth** or **Quick Share** (Android) or **AirDrop** (iPhone, to Apple
  devices only). The send is written to the activity log once the phone is back
  online, with the time it happened. Word, Excel and PowerPoint files can't go
  straight to the share menu on Android: the app saves them to Downloads, then
  send them from the phone's Files app (press and hold the file → Share).
- **Download** is still there on every file, and works as on the website.
- **When copies are removed from the phone:** when you sign out of the app,
  when you lose access to a file, when another officer signs in on the same
  phone, or after 30 days without connecting to the system. A phone may also
  clear saved data by itself, so use Download for a file you must not lose.

---

## Dashboard

The landing page after login. It shows:

- **Headline numbers**: storage used, files (with this week's uploads) and
  folders. Click a card to open the matching page.
- **Activity**: files uploaded per day over the last 30 days.
- **Needs attention**: things someone should act on, such as scan batches
  waiting for review, files whose text couldn't be read, possible copies and
  folders nobody has opened in 90 days. Click a line to go there. "All
  clear" means there is nothing to do.
- **Recent uploads**: the newest files in folders you can open.
- **Storage by type** and a 7-day **Student page** snapshot (visits,
  announcement views, document opens, searches).

The Super Admin's dashboard also has the number of officers, the latest
actions from the Activity Logs, and backup status, because those pages are
Super Admin only.

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

### Ending a school year

There's no separate "start new year" step on the Folders page. When the Super
Admin ends a school year's officer term (Manage Admins → End Term), that same
school year's folders and files are automatically stored away too, clearing
the Folders page for the new term. The stored year stays reachable from
"Previous School Years". This does not delete anything.

## Uploading files

Open the folder you want, use the upload option, then either click to browse or
drag files onto the drop zone. Uploads go straight to cloud storage and show
progress in a small toast in the top-right corner; you can keep working while
they finish. If a direct upload fails it automatically retries through the
server. Accepted types include PDF, Word, Excel, PowerPoint, and common images.

### "Possible copy" label

The system checks every new file against the files already saved, so the
same minutes or resolution isn't kept twice under different names:

- **Exact copy** (the very same file): the upload toast says so right away.
- **Same text** (for example the Word and the PDF of one resolution, a
  re-scan, or a renamed "- Copy"): found a few seconds later, once the
  system has read the file's text.

Either way the newer file gets a **Possible copy** label in its folder. Click
the label to open the older file and compare. Then either delete the copy
(menu → Delete, it goes to Trash) or choose **Not a copy, keep both** if
they are really two documents, such as two resolutions made from the same
template. Nothing is deleted automatically. The list of all possible copies
is on Reports & Analytics, and the Super Admin dashboard shows how many
there are.

## Making a file public (sharing with students)

Every file starts private (officers only). To share one with students, open its
folder, then the file's three-dot menu → Make Public, and confirm. The file then
appears under "Public documents" on the student page, and students who turned on
notifications get an alert on their device. Check you picked the right file
first: an alert that has gone out cannot be taken back.

- **Take it back off the student page:** same menu → Make Private.
- **Restricted folders:** a file inside a restricted folder is never shown to
  students and sends no alert, even when it is marked public.
- **Who can do this:** the folder's creator or a Super Admin.
- **Files still subject for approval** cannot be made public. Mark the file as
  approved first.

## Sharing a file with someone outside the system (share link)

For one person or office that has no account, like the school office or an
adviser from another department. They get a link that opens just that one file,
without signing in.

- **On the website:** open the folder, then the file's three-dot menu → Share
  link. Or open the file and click **Share**. Pick how long the link works (1
  day, 7 days or 30 days), click "Make link", then Copy and send it. On Chrome
  or Edge there is also "Send with an app…", which opens the computer's own
  share panel.
- **On the phone app:** open the file, tap the Share icon at the top, choose how
  long the link works, then "Send a link". The phone's share menu opens
  (Messenger, Gmail...). If it doesn't, tap "Share link" or "Copy".
- **Turn a link off early:** open Share for the same file. Under "Links that
  work now", click "Turn off". The officer who made the link, the folder's
  creator, or a Super Admin can do this.
- **A link also stops working** when it expires, when the file goes to Trash or
  is archived, when the folder becomes restricted (unless its creator or a Super
  Admin made the link), or when the term of the officer who made it ends.
- **Restricted folders:** only the folder's creator or a Super Admin can make a
  link for a file inside one.
- **Files still subject for approval** can be shared this way, for example to
  ask for a signature. The page the link opens says it is a draft.
- **Who opened it:** Share shows how many times each link was opened and
  downloaded. Making and turning off a link go in the activity log.
- **Send the file itself (phone app):** Share → "Send the file" sends a copy
  through the phone's share menu. A copy can't be taken back, so a link is
  safer.

## Files still subject for approval

Sometimes a file is uploaded before it is official, for example a draft
resolution or a project proposal still waiting for signatures. Label it so
nobody mistakes it for an approved document.

- **When uploading:** tick "Still subject for approval" in the upload window.
- **After uploading:** open the folder, then the file's three-dot menu → Mark as
  subject for approval.
- **What the label does:** an amber "Subject for approval" tag shows beside the
  file in its folder, in search results, and as a notice at the top of the file
  when you open it. The file is kept off the student page while it has the
  label. If it was already public, labelling it takes it off the student page.
- **Once it is approved:** three-dot menu → Mark as approved, or open the file
  and click "Mark as approved" on the notice. The label goes away and the file
  can then be made public.
- **Who can do this:** any officer who can open the file can add the label. Only
  the folder's creator or a Super Admin can mark it as approved.

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
- **Search inside archives:** the search bar at the top also finds files that
  are inside an archive – by file name, by the words in the document, by its
  document details (title, reference number, sender, recipient, notes), or by
  school year. They appear under "Files in Archived Folders"; click one to
  preview it. Folders archived before October 2026 are found by file name only.
- **Restore:** an archive can be restored from its menu. The folder comes back
  as it was: same school year, same owner, same access restriction, and its
  files keep their document records and public / approval status. A folder
  that had been stored under a previous school year returns there, not to the
  Folders page. Folders archived before October 2026 come back with their
  files, name, owner and restriction only.
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

## Activity Proposals

Write a Detailed Activity Proposal in the system instead of in Word: Activity
Proposals in the menu → New proposal. Only the title is required, so you can
save and finish the rest later.

- **The top:** Title, Theme, Date, and Proponent (it starts as "Student
  Government"). The Budget is filled in for you from the Grand Total.
- **The five parts:** I. Rationale, II. Objectives, III. Methods & Approaches,
  IV. Schedule of Activities, and V. Budgetary Requirements. Start each
  paragraph on a new line. Objectives can have text before the numbered list.
  Use "Add objective" or "Add row" under a part for more lines, and the ×
  button to remove one.
- **Schedule of Activities:** Time, Activity and Venue/Personnel. A cell can
  have more than one line (for example a name, then the position on the next
  line). The Master of Ceremony goes in its own box under the table and is
  printed as the last row.
- **Budgetary Requirements:** each row has a Category, Particular, Quantity,
  Unit and Unit Price. The Total Price of each row (Quantity × Unit Price) and
  the Grand Total are computed for you. Category and Unit suggest names used
  in earlier proposals, but you can type any name. In the PDF, rows next to
  each other with the same Category share one Category cell.
- **Signatories:** Prepared by, Noted, Budget Available, Recommending
  Approval, and Approved, each with names and positions. A new proposal
  starts with the names from the last proposal, so check them when officers
  change.
- **Print it:** open the proposal, pick the letterhead next to "Print on",
  and click Open PDF. It opens in a new tab, ready to print. Signatures are
  done on the printed copy.
- **Start from last year's proposal:** on the Activity Proposals page, pick
  "All school years" (or last year) and click Show, then Copy on the old
  proposal. You get a new proposal of your own with the same content and no
  dates. Set the dates and check the names and prices.
- **Search:** the search box on the page finds proposals by title, theme,
  particular or category.
- **Who can change it:** the officer who made it, or the Super Admin. Any
  officer can open, print and copy any proposal.
- Approving a proposal in the system and showing it on the student page are
  not available yet.

## Reports & Analytics

Read-only charts and counts in two tabs: **Records & system** (files uploaded,
documents digitized, the scan pipeline, storage, archives) and **Student
engagement** (how students use the public page). Use it for accomplishment
reports at the end of a term.

What you see depends on your role. Officer activity (who did what, the busiest
hours and days), backup status, and sign-in security appear only for the Super
Admin, the same as the Activity Logs and Backup pages those numbers come from.
The CSV export follows the same rule.

The Records tab also has **Found in the documents' text**, worked out from the
words inside the files you can open: possible copies of the same document, a
map of which documents read alike, files that read more like another folder
(possibly misfiled), and the main words of each folder. These are suggestions
only; nothing is moved or deleted for you.

## Announcements

Public notices shown on the student-facing dashboard. Create one with a title,
body, and optional attachment; it becomes visible to students immediately, and
students who turned on notifications get an alert on their device (for a new
announcement only, not for an edit). Edit or delete from the same page.

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
change on first login), edit an officer, or end one officer's term. Ended
officers move to Former Officers and can be reactivated.

"End Term" also lets you pick a whole school year and move every officer
active in it to Former Officers at once - this also stores away that school
year's folders and files, clearing the Folders page for the new term. Add
the incoming batch of officers first, confirm they can log in, then end the
outgoing year - officers and folders/files from other school years are left
untouched.

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
- **"How do I start fresh for the new school year?"** Ask the Super Admin to end
  the outgoing school year's term in Manage Admins → End Term - that stores away
  that year's folders and files and clears the Folders page automatically.
- **"Upload is stuck / failed."** It retries through the server automatically;
  if it still fails, check your connection and file type, then try again.


