jQuery(document).ready(function ($) {
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
    var enabled = $("#cloudflare_apo_enabled").is(":checked");
    var cf_enabled = $("#cloudflare_enabled").is(":checked");
    var should_enable = enabled && cf_enabled;
    $(".apo-field")
      .prop("disabled", !should_enable)
      .css({
        opacity: should_enable ? 1 : 0.5,
        cursor: should_enable ? "auto" : "not-allowed",
      });
    $(".apo-field").each(function () {
      var $th = $(this).closest("tr").find("th");
      $th.css("color", should_enable ? "" : "#999");
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
  $("#cloudflare_apo_enabled").change(toggleAPOFields);

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
    testConnection();
  });
  $("#apply-wp-settings").click(function () {
    applyWordPressSettings();
  });

  // Dev mode functionality
  $("#global-dev-mode-toggle").click(function () {
    globalToggleDevMode();
  });

  // APO toggle functionality
  $("#toggle-apo").click(function () {
    toggleAPO();
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
          showNotice("Cache purged successfully!", "success");
        } else {
          showNotice("Error purging cache: " + response.data, "error");
        }
      }
    );
  }

  function testConnection() {
    $.post(
      illios_cache_ajax.ajax_url,
      {
        action: "illios_cache_test_connection",
        nonce: illios_cache_ajax.nonce_test,
      },
      function (response) {
        if (response.success) {
          showNotice("Connection successful!", "success");
        } else {
          showNotice("Connection failed: " + response.data, "error");
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
          showNotice("WordPress settings applied successfully!", "success");
        } else {
          showNotice("Error applying settings: " + response.data, "error");
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
          showNotice("Development mode toggled successfully!", "success");
          updateGlobalDevModeStatus();
        } else {
          showNotice(
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
    $.post(
      illios_cache_ajax.ajax_url,
      {
        action: "illios_cache_toggle_apo",
        nonce: illios_cache_ajax.nonce_apo,
      },
      function (response) {
        if (response.success) {
          showNotice("APO toggled successfully!", "success");
        } else {
          showNotice("Error toggling APO: " + response.data, "error");
        }
      }
    );
  }

  // Check dev mode status on page load
  updateGlobalDevModeStatus();

  // Update dev mode status every minute if enabled
  setInterval(updateGlobalDevModeStatus, 60000);

  // Utility function for notices
  function showNotice(message, type) {
    var noticeClass = type === "error" ? "notice-error" : "notice-success";
    var notice = $(
      '<div class="notice ' +
        noticeClass +
        ' is-dismissible"><p>' +
        message +
        "</p></div>"
    );
    $(".wrap h1").after(notice);

    setTimeout(function () {
      notice.fadeOut(function () {
        notice.remove();
      });
    }, 5000);
  }
});
