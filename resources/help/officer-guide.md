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
  Logs, and Backup. Some restricted folders are only visible
  to their owner and the Super Admin.

## Signing in

Go to the login page and use the email and password given to you by the Super
Admin. On first login you may be asked to change your password. Forgot it? Use
"Forgot password" on the login page to get a reset link by email. No access to
that email? Ask the Adviser or President for a temporary password; you will
choose your own at the next login.

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
  waiting for review, files whose text couldn't be read and possible copies.
  Click a line to go there. "All clear" means there is nothing to do.
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

### Adding old records to an earlier school year

Records from before they were kept in the system (an old resolution, last
year's minutes) can be added under the school year they belong to, for the
last 5 school years only.

- **Start a year:** Folders page → Previous School Years → "Add an earlier
  school year", pick the year and name its first folder.
- **More folders:** open that year from Previous School Years → New folder.
- **Add files:** open a folder of that year and upload as usual, or file a
  scanned hardcopy into it from Digitize Hardcopy (the folder list shows past
  school years at the bottom).
- **Afterwards:** every folder and file added this way says "Added later by"
  who added it, and when. Officers can't rename or delete them; only the
  Super Admin can rename or move to Trash something added by mistake. Records
  made during their own school year can't be changed by anyone, and past-year
  files can't be put on the student page.

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

When the file has the same text but was saved after the original (for
example a fixed resolution saved as "Reso-22-revised.docx"), the label says
**Possibly a newer draft** instead. Its menu then has **Make it version N of
the original**: the draft stops being listed on its own and becomes the
newest version of the original file (see File versions below). The system
never does this by itself, because two different resolutions made from the
same template can also look alike.

## File versions

A document that comes back for fixes (wrong margins, wrong font, a missing
signatory) stays as **one file** in its folder. Each fixed copy becomes its
next version, and every earlier version is kept.

- **Uploading a file with the same name** into the same folder asks what to
  do: **Save as version N** (the file keeps its name and gets a "v2" label),
  **Keep both as separate files** (saved as "name (1)", like before), or
  **Don't upload this file**. You can write what changed, for example
  "Fixed margins per Adviser".
- **Menu → Versions** (or click the "v2" label) lists every version with
  who uploaded it, when, and the note. Any version can be opened or
  downloaded. **Make current** brings an older version back as a new
  version, so nothing in the history is lost.
- **Menu → Upload new version** adds a version from any file, also one with
  another name or type, such as the signed PDF of a Word draft. The file's
  name stays; its extension follows the new version.
- **Menu → Mark as final** labels the current version **Final**, for the
  version the Adviser accepted or the signed copy. Uploading over it asks
  first, and the Final label stays on the version that was accepted.
- **Editing a document made from Document Templates** saves the edited PDF
  as a new version too ("Edited with the form").

Only the current version shows in the folder, in search, on the student
page, in share links and in the phone app. Older versions are only in the
Versions list. Nothing is deleted when a new version is added: old versions
go away only when the file itself is deleted forever from Trash. Files
stored under a past school year are read-only, so their versions can be
opened but not changed. The phone app's upload still saves a same-name
file as "name (1)".

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

A school year's records are archived together: everything one administration
kept stays as one set.

- **Archive one folder:** not possible any more. A folder is archived together
  with the rest of its school year, as below.
- **How a school year gets there:** End Term moves a school year's folders to
  Previous School Years (read-only, still searchable). Three years after that
  school year ends, the whole year is archived automatically, at night. The
  Adviser or President can archive a finished school year sooner: Archives →
  "Waiting to be archived" → Archive now. A school year whose End Term was not
  run yet can't be archived. Empty folders have nothing to archive and stay.
- **Browse an archive:** Archives page → open a school year to see its
  folders, open a folder to see its files, and preview individual files
  without downloading or restoring anything.
- **Search inside archives:** the search bar at the top also finds files that
  are inside an archive – by file name, by the words in the document, by its
  document details (title, reference number, sender, recipient, notes), or by
  school year. They appear under "Files in Archived Folders"; click one to
  preview it. Folders archived before October 2026 are found by file name only.
- **Restore (Adviser or President):** open the school year → Restore school
  year. All of its folders come back together, under Previous School Years
  for that year, as they were: same owner, same access restriction, and the
  files keep their document records and public / approval status. A restored
  school year is not archived again automatically; it stays until someone
  archives it again from Archives. Folders archived before October 2026 come
  back with their files, name, owner and restriction only.
- **Integrity:** archives are checksummed. If a checksum no longer matches you
  will see an integrity warning – tell the Super Admin.

## Trash

A recycle bin for deleted files, folders, documents, and activity proposals.
Anyone with access can restore an item (any officer can restore a proposal). Permanent deletion is Super Admin only. Items may be cleared
automatically after a retention period.

## Document Templates & Letterheads

Make a resolution or a request letter as a PDF: Document Templates in the menu →
pick Resolution or Request letter, fill in the form, and Save document. The
preview on the right shows the page as you type; Preview PDF opens the real PDF
without saving it.

- **Resolution:** laid out like the SG's own (number and series, title,
  Authored by, WHEREAS, WHEREFORE, RESOLVED, ADOPTED, then the signatures from
  "Certified true and correct" to "Approved"). Each WHEREAS and RESOLVED clause
  has its own box; type it without the lead word. Pasting a whole drafted
  resolution into a WHEREAS box splits it into the boxes for you. The Resolution
  No. is suggested from the adoption date, the school year and the next running
  number, e.g. 04-06-26 (2526-034); check it and change it if needed.
- **Bold words:** put two asterisks around them, like **this**.
- **Signatures:** a new document starts with the names from the last one, so
  check them when officers change. Tick "Print (SGD)" for the copy posted online
  after it is signed.
- **Fix or reuse a saved one:** under "Saved to records", Edit opens it again
  and saving puts the new PDF in its place; Copy starts a new document from it.

Letterheads are the page design placed around the document – manage them from
Manage letterheads on the same page.

- **Sharp print:** open the SG letterhead in Word, set the paper to Long
  (8.5 × 13 in), File → Save as → PDF, and upload that PDF as the "Whole page".
  A screenshot prints blurry; the letterhead's card warns when an image is too
  small or not Long size.
- **Default:** press "Set as default" on the SG's current letterhead. New
  resolutions, request letters and activity proposal PDFs start with it, and
  the Reports & Analytics PDF is printed on it.
- **New school year:** press "Copy" on last year's letterhead, upload the new
  design under Edit, then set the copy as the default.

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
- **Price check:** while you type the budget, each Unit Price is compared with
  what approved proposals paid for the same item and unit (Approved, Done, or
  Cancelled after approval; drafts don't count), from this school year and the
  3 before. Close names count as one item ("Lunch", "Packed lunch"). Older
  prices are first brought up to today's value for inflation. A price far from
  the usual one (more than about 35% off) gets an orange ⚠ mark in its row,
  and the **Price check** box under the budget says why, e.g. "2.2× the usual
  ₱65". It is only a warning: fix the price, or click **Price is correct** in
  that box and write why (a different caterer, drinks included). The reason
  is saved with the row and shown on the proposal's page and in Reports, not
  in the PDF; change the price and the reason is cleared. An item needs 3 or
  more past prices before it is checked.
- **Often budgeted together:** the box beside Price check suggests items that
  approved proposals with the same items also had, e.g. "Trophies: 4 of the 5
  approved proposals with Medals had it too." Click Add row to add one with
  its usual category and unit. Suggestions start once there are 5 approved
  proposals. Proposals imported from Word count once they are set to
  Approved.
- **Signatories:** Prepared by, Noted, Budget Available, Recommending
  Approval, and Approved, each with names and positions. A new proposal
  starts with the names from the last proposal, so check them when officers
  change.
- **Print it:** open the proposal, pick the letterhead next to "Print on",
  and click Open PDF. It opens in a new tab, ready to print. Signatures are
  done on the printed copy.
- **Import from Word:** for a proposal written in Word before the system,
  click **Import from Word** on the Activity Proposals page and choose the
  .docx file. The system reads the Title, Theme, Date, Proponent, parts I to V,
  the budget table (merged Category cells included) and the signatories, and
  fills in a new proposal. Check it, then click Save proposal; nothing is
  saved before that, and the Word file is not kept. Budget rows whose Total
  Price in the file doesn't equal Quantity × Unit Price are marked, and so is
  a Grand Total that doesn't add up. A .doc file must be saved as .docx in
  Word first; scanned paper can't be read yet.
- **Start from last year's proposal:** on the Activity Proposals page, pick
  "All school years" (or last year) and click Show, then Copy on the old
  proposal. You get a new proposal of your own with the same content and no
  dates. Set the dates and check the names and prices.
- **Search:** the search box on the page finds proposals by title, theme,
  particular or category. Pick a status next to it to see, for example, only
  the ones still For signing.
- **Status:** shows where the proposal is: Draft (still being written), For
  signing (printed and going around for signatures), Approved, Not approved,
  Done (the activity was held) or Cancelled. Set it on the proposal's page
  under "Status and signed copy". Done comes after Approved.
- **Signed copy:** once the paper is signed, upload a PDF or photo of it on the
  proposal's page. It is saved in the **Activity Proposals** folder (made for
  you the first time) and the proposal becomes Approved. A scan already filed
  into that folder from the Scan Inbox can be chosen instead of uploading
  again.
- **Approved means locked:** an approved proposal can't be edited or deleted,
  so it stays the same as the signed paper. It can still be printed and
  copied, and set to Done or Cancelled. If the signed version has to be
  redone, the Super Admin can press **Reopen for changes**; it becomes a draft
  again.
- **Who can change it:** the officer who made it, or the Super Admin, for
  editing, the status, the signed copy and deleting. Any officer can open,
  print and copy any proposal.
- **Delete:** a proposal that isn't approved can be deleted; it goes to the
  Trash and can be restored from there. Its signed copy, if any, stays in its
  folder.
- **Student page:** on the proposal's page, click **Show on student page**.
  It appears under **Activities** on the student page once it is Approved (or
  Done, or Cancelled after approval); a draft or one still For signing never
  shows, even when switched on. Students see the title, theme, date, the
  objectives and the budget per category (not the line items). Names,
  signatures and the signed copy are never shown. Click **Hide from student
  page** to take it off. Activity pages are not listed on Google.

## Reports & Analytics

Read-only charts and counts in three tabs: **Records & system** (records added,
scanning, storage, system health, inactive folders, possible copies and files
in the wrong folder), **Student engagement** (visits, unusual days, when
students visit, top announcements, most used documents and student searches)
and **Proposals** (unusual prices and items often budgeted together). Use it
for accomplishment reports at the end of a term. Each panel has a short title,
and the line under it says what the numbers show.

- **Proposals tab:** *Unusual prices* lists every budget price in the
  period's proposals that the price check flags (see Activity Proposals), with
  the officer's reason or "None yet". *Often budgeted together* lists the
  strongest patterns in all approved proposals, found by association rule
  mining (Apriori): Together (how many proposals had both), Confidence (of the
  proposals with the first items, the share that also had the second) and Lift
  (above 1 means they go together more than by chance). Any officer sees this
  tab. The Super Admin sets the inflation rate (from the PSA, 3% to start) and
  how many school years back prices are compared, under "Change these
  settings".

- **Print it:** pick the period, then click **Open PDF** next to Export CSV.
  The open tab opens as a report in a new tab, on the default letterhead (see
  Letterheads), ready to print or save. The charts are printed as tables. Left
  out of the PDF: the accounts and IP addresses under Suspicious sign-ins
  (only how many there are).

- **Unusual days:** the Student engagement tab lists days when far more
  students than usual opened the student page, compared with the 4 weeks
  before each day, and what was posted or opened that day. A day needs at
  least 10 visits and twice a usual day to count, and the page needs 2 weeks
  of recorded visits before any day can be judged.

What you see depends on your role. Officer activity (the kinds of work and who
did the most), backup status, and sign-in security appear only for the Super
Admin, the same as the Activity Logs and Backup pages those numbers come from.
The CSV export and the PDF follow the same rule.

The Records tab also has **Found in the documents' text**, worked out from the
words inside the files you can open: possible copies of the same document,
and files that may be in the wrong folder. These are suggestions only; nothing is moved or
deleted for you.

- **Wrong folder, by title:** a file whose title says what it is ("A
  RESOLUTION", "MINUTES OF THE MEETING", "ACCOMPLISHMENT REPORT") but sits in a
  folder named for another kind, e.g. a resolution in Reports. Folders named
  after an event or anything else may hold any kind of document.
- **Wrong folder, by words:** a file without such a title that reads much more
  like the files of another folder than its own. A copy of the same file is
  never counted, so a copy in another folder doesn't make the original look
  misfiled.

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

## The menu (sidebar)

The menu on the left groups the pages: **Overview** (Dashboard, Reports &
Analytics), **Records** (Folders, Documents, Scan Inbox, Archives, Trash),
**Create** (Document Templates, Activity Proposals) and **Student page**
(Announcements, Site Content). The Super Admin also sees **Administration**.
A number next to Scan Inbox is how many scans are waiting for review.

- **Smaller menu:** the « button at the bottom (or the [ key) shrinks the menu
  to icons, to give pages more room. Press it again to bring the names back.
  Your choice is remembered on that browser.
- **On a phone:** tap the ☰ button at the top left to open the menu; tap
  outside it to close it.
- **Profile, keyboard shortcuts and Sign Out:** click your name at the bottom
  of the menu.

## Using the keyboard

Press **?** on any page to see the list of keyboard shortcuts (or click your
name at the bottom of the menu, then "Keyboard shortcuts"). Shortcuts don't
work while you are typing in a box; press Esc first.

- **? :** show the list of shortcuts.
- **G, then a letter:** open a page. Press G and the letters appear next to
  the pages in the menu for two seconds: **H** Dashboard (home), **R** Reports
  & Analytics, **F** Folders, **D** Documents, **I** Scan Inbox, **A**
  Archives, **B** Trash (recycle bin), **T** Document Templates, **P**
  Activity Proposals, **N** Announcements (news), **C** Site Content. The
  Super Admin also has **M** Manage Admins, **L** Activity Logs and **S**
  Backup. Pointing at a page in the menu also shows its letter.
- **/ :** jump to the search box at the top.
- **[ :** shrink or expand the menu (on a laptop).
- **Esc:** close a menu, a window or the shortcut list.
- **Arrow keys, Page Down / Page Up, Space, Home / End:** scroll the page.
- **Tab / Shift+Tab:** move to the next or previous link, button or box;
  **Enter** opens or presses it. Right after a page opens, pressing Tab then
  Enter skips the menu and goes straight to the page.

---

## Super Admin only

### Manage Admins

Add a new officer (name, email, position – they get a temporary password to
change on first login), edit an officer, or end one officer's term. Ended
officers move to Former Officers and can be reactivated. Former officers can't
log in, and anyone still logged in when their term ends is logged out.

Edit an officer to change their name, email or position. The school year is
locked there. **Reset Password** (on the same page) is for an officer who can't
use "Forgot password": type a temporary password and give it to them. They must
choose a new one at their next login, every device where they were logged in is
logged out, and the reset is recorded in Activity Logs. Your own password is
changed from Profile.

"End Term" also lets you pick a whole school year and move every officer
active in it to Former Officers at once - this also stores away that school
year's folders and files, clearing the Folders page for the new term. Add
the incoming batch of officers first, confirm they can log in, then end the
outgoing year - officers and folders/files from other school years are left
untouched.

### Activity Logs

A timeline of who did what – uploads, deletions, renames, logins, access
changes. Use it to trace a change or for audit questions.

### Backup

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


