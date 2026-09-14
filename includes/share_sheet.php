<?php
/**
 * The share sheet, included once per page that can open it.
 *
 * A partial rather than a fourth block of copy-pasted markup: index.php,
 * map.php and post.php all need it, and the listing modal — which is duplicated
 * byte-for-byte across index.php and map.php — is the standing example of what
 * happens when that markup is pasted instead of included.
 *
 * The tiles themselves are built in initShare() rather than here, because which
 * ones apply is a property of the browser, not the page: "Share to…" only means
 * anything where navigator.share exists, and the Instagram and Snapchat tiles
 * behave differently depending on whether the device can share a file.
 */
?>
<div class="share-sheet-overlay" id="shareSheet" aria-hidden="true">
    <div class="share-sheet" role="dialog" aria-modal="true" aria-labelledby="shareSheetTitle">
        <div class="share-sheet-head">
            <h2 class="share-sheet-title" id="shareSheetTitle">Share this listing</h2>
            <button type="button" class="share-sheet-close" id="shareSheetClose" aria-label="Close share menu">&times;</button>
        </div>

        <div class="share-grid" id="shareGrid"></div>

        <div class="share-link-row">
            <?php /* Readonly rather than disabled: the value still has to be
                     selectable by hand on a browser where the clipboard API is
                     unavailable, which is the fallback of last resort. */ ?>
            <input type="text" class="share-link-input" id="shareLinkInput" readonly
                   aria-label="Share link" value="">
            <button type="button" class="btn btn-secondary btn-sm share-copy" id="shareCopyBtn">
                <i class="fa-solid fa-link"></i> Copy
            </button>
        </div>

        <p class="share-sheet-note" id="shareSheetNote">
            Anyone with this link sees the price and how far it is from campus.
            The address and your contact details stay behind UVM sign-in.
        </p>
    </div>
</div>
