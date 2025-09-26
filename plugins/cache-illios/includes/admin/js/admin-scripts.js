jQuery(document).ready(function ($) {
  // Create sticky notice container on page load
  if (!$(".sticky-notice-container").length) {
    $("body").append('<div class="sticky-notice-container"></div>');
  }

  // Field Toggle Functions
  function toggleCloudflareFields() {
    var enabled = $("#cloudflare_enabled").is(":checked");
    $(".cloudflare-field")
      .prop("disabled", !enabled)
      .css({
        opacity: enabled ? 1 : 0.5,
        cursor: enabled ? "auto" : "not-allowed",
      });
    $(".cloudflare-field").each(function () {
      var $th = $(this).closest("tr").find("th");
      $th.css("color", enabled ? "" : "#999");
    });
    $(".cloudflare-advanced-btn")
      .prop("disabled", !enabled)
      .css({
        opacity: enabled ? 1 : 0.5,
        cursor: enabled ? "auto" : "not-allowed",
      });
  }

  function toggleVarnishFields() {
    var enabled = $("#varnish_enabled").is(":checked");
    $(".varnish-field")
      .prop("disabled", !enabled)
      .css({
        opacity: enabled ? 1 : 0.5,
        cursor: enabled ? "auto" : "not-allowed",
      });
    $(".varnish-field").each(function () {
      var $th = $(this).closest("tr").find("th");
      $th.css("color", enabled ? "" : "#999");
    });
  }

  function toggleAPOFields() {
    var cf_enabled = $("#cloudflare_enabled").is(":checked");
    var apo_available =
      $("#cloudflare_apo_enabled").attr("data-cf-available") === "1";

    // enable the APO "enable" checkbox only when Cloudflare integration is enabled AND APO is available
    var enableApoCheckbox = cf_enabled && apo_available;
    $(".apo-enable-field")
      .prop("disabled", !enableApoCheckbox)
      .css({
        opacity: enableApoCheckbox ? 1 : 0.5,
        cursor: enableApoCheckbox ? "auto" : "not-allowed",
      });

    // ensure the label for the enable checkbox reflects the enabled state (remove server-side inline disabled style)
    $(".apo-enable-field").each(function () {
      var $input = $(this);
      var $label = $('label[for="' + $input.attr("id") + '"]');
      if (enableApoCheckbox) {
        $label.css({ color: "", cursor: "" }).removeAttr("style");
      } else {
        $label.css({ color: "#999", cursor: "not-allowed" });
      }
    });

    // cache-by-device should be enabled only when APO checkbox is enabled and checked
    var apo_checked = $("#cloudflare_apo_enabled").is(":checked");
    var enableCacheBy = cf_enabled && apo_available && apo_checked;
    $(".apo-cacheby-field")
      .prop("disabled", !enableCacheBy)
      .css({
        opacity: enableCacheBy ? 1 : 0.5,
        cursor: enableCacheBy ? "auto" : "not-allowed",
      });

    // ensure the label for cache-by reflects the enabled state
    $(".apo-cacheby-field").each(function () {
      var $input = $(this);
      var $label = $('label[for="' + $input.attr("id") + '"]');
      if (enableCacheBy) {
        $label.css({ color: "", cursor: "" }).removeAttr("style");
      } else {
        $label.css({ color: "#999", cursor: "not-allowed" });
      }
    });

    // adjust th label color for both field types
    $(".apo-enable-field, .apo-cacheby-field").each(function () {
      var $th = $(this).closest("tr").find("th");
      var fieldDisabled = $(this).prop("disabled");
      $th.css("color", fieldDisabled ? "#999" : "");
    });
  }

  // Initialize field states
  toggleCloudflareFields();
  toggleVarnishFields();
  toggleAPOFields();

  // Bind change events
  $("#cloudflare_enabled").change(function () {
    toggleCloudflareFields();
    toggleAPOFields();
  });
  $("#varnish_enabled").change(toggleVarnishFields);

  $("#cloudflare_apo_enabled").change(function () {
    toggleAPOFields();
  });

  // Cache purge functionality
  $("#purge-cloudflare").click(function () {
    purgeCache("cloudflare");
  });
  $("#purge-varnish").click(function () {
    purgeCache("varnish");
  });
  $("#purge-all").click(function () {
    purgeCache("all");
  });

  // Advanced Cloudflare management
  $("#test-cloudflare").click(function () {
    testCloudflareConnection();
  });
  $("#apply-wp-settings").click(function () {
    applyWordPressSettings();
  });

  // Advanced Varnish management
  $("#test-varnish").click(function () {
    testVarnishConnection();
  });

  // Dev mode functionality
  $("#global-dev-mode-toggle").click(function () {
    globalToggleDevMode();
  });

  // Core AJAX Functions
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

  function testCloudflareConnection() {
    $.post(
      illios_cache_ajax.ajax_url,
      {
        action: "illios_cache_test_connection",
        nonce: illios_cache_ajax.nonce_test,
      },
      function (response) {
        if (response.success) {
          showStickyNotice("Connection successful!", "success");
        } else {
          showStickyNotice("Connection failed: " + response.data, "error");
        }
      }
    );
  }

  function applyWordPressSettings() {
    $.post(
      illios_cache_ajax.ajax_url,
      {
        action: "illios_cache_apply_wp_settings",
        nonce: illios_cache_ajax.nonce_wp,
      },
      function (response) {
        if (response.success) {
          showStickyNotice(
            "WordPress settings applied successfully!",
            "success"
          );
        } else {
          showStickyNotice(
            "Error applying settings: " + response.data,
            "error"
          );
        }
      }
    );
  }

  function testVarnishConnection() {
    $.post(
      illios_cache_ajax.ajax_url,
      {
        action: "illios_cache_test_varnish_connection",
        nonce: illios_cache_ajax.nonce_test,
      },
      function (response) {
        if (response.success) {
          showStickyNotice("Varnish connection successful!", "success");
        } else {
          showStickyNotice(
            "Varnish connection failed: " + response.data,
            "error"
          );
        }
      }
    );
  }

  function globalToggleDevMode() {
    var $button = $("#global-dev-mode-toggle");
    var originalText = $button.text();
    $button.text("Processing...").prop("disabled", true);

    $.post(
      illios_cache_ajax.ajax_url,
      {
        action: "illios_cache_toggle_dev_mode",
        nonce: illios_cache_ajax.nonce_dev_mode,
      },
      function (response) {
        if (response.success) {
          showStickyNotice("Development mode toggled successfully!", "success");
          updateGlobalDevModeStatus();
        } else {
          showStickyNotice(
            "Error toggling development mode: " + response.data,
            "error"
          );
          $button.text(originalText).prop("disabled", false);
        }
      }
    );
  }

  function updateGlobalDevModeStatus() {
    $.post(
      illios_cache_ajax.ajax_url,
      {
        action: "illios_cache_get_dev_mode_status",
        nonce: illios_cache_ajax.nonce_dev_mode_status,
      },
      function (response) {
        var $button = $("#global-dev-mode-toggle");
        var $status = $("#global-dev-mode-status");
        var $info = $("#global-dev-mode-info");

        if (response.success) {
          var data = response.data;

          if (data.enabled) {
            var timeRemaining = data.time_remaining;
            var hours = Math.floor(timeRemaining / 3600);
            var minutes = Math.floor((timeRemaining % 3600) / 60);

            $button
              .text("Disable Development Mode")
              .css({
                background: "#d63638",
                "border-color": "#d63638",
                color: "white",
              })
              .prop("disabled", false);

            $info.html(
              "Time remaining: <strong>" +
                hours +
                "h " +
                minutes +
                "m</strong><br>" +
                "Enabled at: " +
                new Date(data.enabled_at * 1000).toLocaleString()
            );
            $status.css("border-left-color", "#d63638").show();
          } else {
            $button
              .text("Enable Development Mode")
              .css({
                background: "",
                "border-color": "",
                color: "",
              })
              .prop("disabled", false);
            $status.hide();
          }
        } else {
          $button.text("Enable Development Mode").prop("disabled", false);
          $status.hide();
        }
      }
    );
  }

  function toggleAPO() {
    console.log("toggleAPO function called");
    $.post(
      illios_cache_ajax.ajax_url,
      {
        action: "illios_cache_toggle_apo",
        nonce: illios_cache_ajax.nonce_apo,
      },
      function (response) {
        if (response.success) {
          showStickyNotice("APO toggled successfully!", "success");
        } else {
          showStickyNotice("Error toggling APO: " + response.data, "error");
        }
      }
    );
  }

  // Check dev mode status on page load
  updateGlobalDevModeStatus();

  // Update dev mode status every minute if enabled
  setInterval(updateGlobalDevModeStatus, 60000);

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

  // Legacy function for backward compatibility (still creates sticky notices)
  function showNotice(message, type) {
    showStickyNotice(message, type);
  }
});
