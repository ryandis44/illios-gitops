jQuery(document).ready(function ($) {
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

  toggleCloudflareFields();
  toggleVarnishFields();
  toggleAPOFields();

  $("#cloudflare_enabled").change(function () {
    toggleCloudflareFields();
    toggleAPOFields();
  });
  $("#varnish_enabled").change(toggleVarnishFields);
  $("#cloudflare_apo_enabled").change(toggleAPOFields);

  $("#purge-cloudflare").click(function () {
    purgeCache("cloudflare");
  });
  $("#purge-varnish").click(function () {
    purgeCache("varnish");
  });
  $("#purge-all").click(function () {
    purgeCache("all");
  });
  $("#test-cloudflare").click(function () {
    testConnection();
  });
  $("#apply-wp-settings").click(function () {
    applyWordPressSettings();
  });

  function purgeCache(type) {
    $.post(
      illios_cache_ajax.ajax_url,
      {
        action: "illios_cache_purge",
        type: type,
        nonce: illios_cache_ajax.nonce_purge,
      },
      handleResponse
    );
  }

  function testConnection() {
    $.post(
      illios_cache_ajax.ajax_url,
      {
        action: "illios_cache_test_connection",
        nonce: illios_cache_ajax.nonce_test,
      },
      handleResponse
    );
  }

  function applyWordPressSettings() {
    $.post(
      illios_cache_ajax.ajax_url,
      {
        action: "illios_cache_apply_wp_settings",
        nonce: illios_cache_ajax.nonce_wp,
      },
      handleResponse
    );
  }

  function handleResponse(response) {
    if (response.success) {
      showNotice("Operation successful!", "success");
    } else {
      showNotice("Error: " + response.data, "error");
    }
  }

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
