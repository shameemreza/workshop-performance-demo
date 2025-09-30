/**
 * Workshop Performance Demo - Admin Scripts
 * Minimal JavaScript enhancements
 *
 * @package Workshop_Performance_Demo
 * @since 1.0.0
 */

(function ($) {
  "use strict";

  $(document).ready(function () {
    // Add copy functionality to code blocks
    addCopyButtons();
  });

  /**
   * Add copy buttons to code blocks
   */
  function addCopyButtons() {
    $("pre").each(function () {
      const $pre = $(this);

      // Create copy button
      const $button = $(
        '<button type="button" class="button button-small" style="float: right; margin: -8px -8px 0 0;">Copy</button>'
      );

      // Add click handler
      $button.on("click", function () {
        const code = $pre.text();
        copyToClipboard(code);

        // Update button text
        $button.text("Copied!");
        setTimeout(function () {
          $button.text("Copy");
        }, 2000);
      });

      // Add button to pre element
      $pre.prepend($button);
    });
  }

  /**
   * Copy text to clipboard
   */
  function copyToClipboard(text) {
    // Create temporary textarea
    const $textarea = $("<textarea>")
      .val(text)
      .css({
        position: "fixed",
        opacity: "0",
      })
      .appendTo("body")
      .select();

    // Copy and remove
    document.execCommand("copy");
    $textarea.remove();
  }
})(jQuery);
