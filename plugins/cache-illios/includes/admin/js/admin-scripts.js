jQuery(document).ready(function ($) {
  // Create sticky notice container on page load
  if (!$(".sticky-notice-container").length) {
    $("body").append('<div class="sticky-notice-container"></div>');
  }

  // Cache purge functionality
  jQuery(document).on("click", "#purge-cloudflare", function () {
    purgeCache("cloudflare");
  });
  jQuery(document).on("click", "#purge-varnish", function () {
    purgeCache("varnish");
  });
  jQuery(document).on("click", "#purge-all", function () {
    purgeCache("all");
  });

  function purgeCache(type) {
    $.post(
      illios_cache_ajax.ajax_url,
      {
        action: "illios_cache_purge",
        type: type,
        nonce: illios_cache_ajax.nonce_purge,
      },
      function (response) {
        if (response.success) {
          showStickyNotice("Cache purged successfully!", "success");
        } else {
          showStickyNotice("Error purging cache: " + response.data, "error");
        }
      }
    );
  }

  // Enhanced sticky notification function
  function showStickyNotice(message, type) {
    var noticeClass = type === "error" ? "notice-error" : "notice-success";
    var noticeId = "notice-" + Date.now();

    var notice = $(
      '<div id="' +
        noticeId +
        '" class="notice ' +
        noticeClass +
        ' is-dismissible sticky-notice-enter">' +
        "<p>" +
        message +
        "</p>" +
        '<button type="button" class="notice-dismiss" aria-label="Dismiss this notice">' +
        '<span class="screen-reader-text">Dismiss this notice.</span>' +
        "</button>" +
        "</div>"
    );

    // Add to sticky container
    $(".sticky-notice-container").append(notice);

    // Animate in
    setTimeout(function () {
      notice
        .removeClass("sticky-notice-enter")
        .addClass("sticky-notice-enter-active");
    }, 10);

    // Add dismiss functionality
    notice.find(".notice-dismiss").click(function () {
      dismissStickyNotice(notice);
    });

    // Auto-dismiss after 5 seconds
    setTimeout(function () {
      dismissStickyNotice(notice);
    }, 5000);
  }

  // Function to dismiss sticky notices with animation
  function dismissStickyNotice(notice) {
    if (notice.length && !notice.hasClass("sticky-notice-exit")) {
      notice
        .removeClass("sticky-notice-enter-active")
        .addClass("sticky-notice-exit sticky-notice-exit-active");

      setTimeout(function () {
        notice.remove();
      }, 300);
    }
  }

  // Clean up old notices periodically (fallback)
  setInterval(function () {
    $(".sticky-notice-container .notice").each(function () {
      var $notice = $(this);
      var age =
        Date.now() - parseInt($notice.attr("id").replace("notice-", ""));

      // Remove notices older than 10 seconds as fallback
      if (age > 10000) {
        dismissStickyNotice($notice);
      }
    });
  }, 5000);

  // Handle window resize to adjust sticky container position
  $(window).on("resize", function () {
    // Force recalculation of admin bar height if needed
    var adminBarHeight = $("#wpadminbar").height() || 32;
    $(".sticky-notice-container").css("top", adminBarHeight + "px");
  });
});
