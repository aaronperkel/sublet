<?php
/**
 * The listing view, shared by Browse (app/index.php) and Map (app/map.php).
 *
 * It used to be pasted into both pages. Everything in it is filled in by
 * openModal() in app.js from a card's data-* attributes or a map pin's row, so
 * nothing here is per listing.
 *
 * On a desktop it is a centered dialog. At 600px and below it is a full-screen
 * sheet: the top bar (close, share) and the bottom bar (price, Email, Call)
 * stay put while the photos and details scroll between them, so the way out
 * and the way to make contact are always one tap away.
 */
?>
<div class="modal-overlay" id="modal" role="dialog" aria-modal="true" aria-label="Listing" aria-hidden="true">
    <div class="modal-container">
        <div class="modal-topbar">
            <button type="button" class="modal-close" id="modalClose" aria-label="Close listing">&times;</button>
            <button type="button" class="modal-topbar-share" id="modalShareTop" aria-label="Share this listing">
                <i class="fa-solid fa-arrow-up-from-bracket" aria-hidden="true"></i>
            </button>
        </div>

        <?php /* touch-action: pan-y (CSS) leaves vertical scrolling to the
                 browser and horizontal swipes to app.js. */ ?>
        <div class="modal-gallery" id="modalGallery">
            <img id="modalImage" alt="">
            <button type="button" class="gallery-nav prev" id="galleryPrev" aria-label="Previous photo"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></button>
            <button type="button" class="gallery-nav next" id="galleryNext" aria-label="Next photo"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></button>
            <div class="gallery-dots" id="galleryDots"></div>
            <span class="gallery-count" id="galleryCount" aria-live="polite"></span>
        </div>

        <div class="modal-details" id="modalDetails">
            <div class="modal-details-inner">
                <div class="modal-header">
                    <div class="modal-heading">
                        <span class="modal-price" id="modalPrice"></span>
                        <p class="modal-facts" id="modalFacts"></p>
                    </div>
                    <div class="modal-actions" id="modalActions">
                        <button type="button" id="modalEmailBtn" class="btn btn-primary btn-sm">
                            <i class="fa-solid fa-envelope" aria-hidden="true"></i> Email
                        </button>
                        <button type="button" id="modalPhoneBtn" class="btn btn-primary btn-sm" hidden>
                            <i class="fa-solid fa-phone" aria-hidden="true"></i> Call
                        </button>
                        <button type="button" id="modalShareBtn" class="btn btn-secondary btn-sm">
                            <i class="fa-solid fa-arrow-up-from-bracket" aria-hidden="true"></i> Share
                        </button>
                        <a id="modalEdit" href="post.php" class="btn btn-gold btn-sm" hidden>
                            <i class="fa-solid fa-pen" aria-hidden="true"></i> Edit
                        </a>
                        <?php if (is_admin()): ?>
                            <button type="button" id="modalDelete" class="btn btn-danger btn-sm">
                                <i class="fa-solid fa-trash" aria-hidden="true"></i> Delete
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-body">
                    <div class="modal-field">
                        <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                        <span>
                            <span id="modalAddress"></span>
                            <a id="modalMapLink" class="modal-map-link" href="#" target="_blank" rel="noopener">Open in Maps</a>
                        </span>
                    </div>
                    <div class="modal-field">
                        <i class="fa-solid fa-calendar" aria-hidden="true"></i>
                        <span id="modalSemester"></span>
                    </div>
                    <div class="modal-description" id="modalDescription"></div>
                    <div class="modal-poster" id="modalPoster"></div>
                </div>
            </div>
            <div class="modal-contact-panel" id="contactPanel">
                <button type="button" class="contact-back-btn" id="contactBackBtn">
                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to listing
                </button>
                <h3 class="contact-panel-title" id="contactPanelTitle">Contact</h3>
                <div id="contactPanelBody"></div>
            </div>
        </div>

        <?php /* Phone only (CSS): the price and the two ways to make contact,
                 pinned to the bottom of the sheet. The buttons forward to the
                 Email and Call buttons above, so there is one contact flow. */ ?>
        <div class="modal-contact-bar" id="modalContactBar">
            <span class="modal-contact-bar-price" id="modalBarPrice"></span>
            <div class="modal-contact-bar-actions">
                <button type="button" class="btn btn-primary" id="modalBarEmail" data-forward="modalEmailBtn">
                    <i class="fa-solid fa-envelope" aria-hidden="true"></i> Email
                </button>
                <button type="button" class="btn btn-primary" id="modalBarCall" data-forward="modalPhoneBtn" hidden>
                    <i class="fa-solid fa-phone" aria-hidden="true"></i> Call
                </button>
            </div>
        </div>
    </div>
</div>
