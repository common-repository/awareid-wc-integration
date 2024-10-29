
jQuery(document).ready(function ($) {
  // Function to handle successful geolocation
  function success(position) {
    var lat = position.coords.latitude;
    var lng = position.coords.longitude;
    var nonce = verification_params.nonce;

    $.ajax({
      type: "post",
      dataType: "json",
      url: verification_params.ajax_url,
      data: {
        action: "check_state",
        latitude: lat,
        longitude: lng,
        nonce: nonce,
      },
      success: function (response) {
        if (response.success) {
          $("#state-checker-result").text(
            "You are located in " + response.data + "."
          );
        } else {
          $("#state-checker-result").text(
            "The point is not in any selected state."
          );
        }
      },
    });
  }

  // Function to handle errors and rejections of geolocation
  function error(err) {
    console.warn(`ERROR(${err.code}): ${err.message}`);
    if (err.code == 1) {
      // User denied Geolocation
      $("#state-checker-result").text(
        "Error: Geolocation is denied by the user."
      );
    } else {
      $("#state-checker-result").text("Error: The Geolocation service failed.");
    }
    var nonce = verification_params.nonce;

    $.ajax({
      type: "post",
      dataType: "json",
      url: verification_params.ajax_url,
      data: {
        action: "check_state",
        latitude: null,
        longitude: null,
        nonce: nonce,
      },
      success: function (response) {
        if (response.success) {
          $("#state-checker-result").text(
            "You are located in " + response.data + "."
          );
        } else {
          $("#state-checker-result").text(
            "The point is not in any selected state."
          );
        }
      },
      error: function (response) {
        $("#state-checker-result").text(
          "Error: Unable to determine IP address."
        );
      }
    });
  }

  // Request the user's current position
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(success, error);
  } else {
    $("#state-checker-result").text(
      "Error: Your browser does not support HTML5 Geolocation."
    );
  }
});
