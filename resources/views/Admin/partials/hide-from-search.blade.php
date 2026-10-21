{{-- "Hide from Google" on the announcement forms (post and edit). Saved as
     announcements.hide_from_search; see StudentController::announcement. --}}
<div class="field-group">
    <label class="check-row">
        <input type="checkbox" name="hide_from_search" value="1" @checked($checked)>
        Hide from Google
    </label>
    <small style="color:var(--text-3);">
        Tick this when the post names students or other people. It still shows on the student page and in shared links;
        search engines are only asked not to list it. A post Google already listed can take a few days to drop out.
    </small>
</div>
