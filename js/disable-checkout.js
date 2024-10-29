jQuery(document).ready(function ($) {
  const isBlockBasedCheckout = () => typeof wp !== 'undefined' && wp.data && wp.data.select('wc/store/checkout');

  const handleCheckoutError = () => {
    const errorMessages = isBlockBasedCheckout()
      ? $('.wc-block-components-validation-error')
      : $('.woocommerce-NoticeGroup-checkout .wc-block-components-notice-banner__content');
    
    const error_count = isBlockBasedCheckout()
      ? errorMessages.length
      : errorMessages.toArray().reduce((count, el) => {
          const listItems = $(el).find('ul li');
          return count + (listItems.length || 1);
        }, 0);

    const billingEmail = $("#billing_email").val() || $("#email").val();

    if (!userData.is_user_logged_in) {
      checkEmailExistence(billingEmail);
    } else if (error_count === 1) {
      showIdentityVerificationModal(billingEmail);
    }
  };

  const checkEmailExistence = (email) => {
    $.ajax({
      url: userData.ajax_url,
      type: "POST",
      data: {
        action: "awareid_check_email_existence",
        email: email,
        nonce: userData.email_check_nonce 
      },
      success: (response) => {
        if (response.success && response.data.exists) {
          console.log("Email exists, redirecting to login");
          setTimeout(() => {
            window.location.href = "/wordpress/my-account";
          }, 3000);
        } else {
          showIdentityVerificationModal(email);
        }
      },
    });
  };

  const showIdentityVerificationModal = (email) => {
    userData.email = email;
    setTimeout(() => {
      $(".woocommerce-loading-modal").modal("hide");
      $("#identityVerificationModal").modal("show");
      handleEnrollmentButton();
    }, 500);
  };

  const interceptCheckout = () => {
    const billingEmail = $("#billing_email").val() || $("#email").val();
    
    if (!userData.is_user_logged_in) {
      $.ajax({
        url: userData.ajax_url,
        type: "POST",
        data: {
          action: "awareid_check_email_existence",
          email: billingEmail,
          nonce: userData.awareid_email_check_nonce
        },
        success: (response) => {
          if (response.success && response.data.exists) {
            showExistingEmailError();
          } else if (response.success) {
            userData.email = billingEmail;
            $("#consentModal").modal("show");
          } else {
            console.error("Error checking email:", response.data);
          }
        },
        error: (jqXHR, textStatus, errorThrown) => {
          console.error("AJAX request failed:", textStatus, errorThrown);
        }
      });
    } else {
      proceedWithCheckout();
    }
  };

  const showExistingEmailError = () => {
    let $targetLocation = $('.wc-block-checkout__actions .wc-block-components-notices');
    $targetLocation.append('<div class="woocommerce-error">Email already exists, please log in. Redirecting in 3 seconds...</div>');
    setTimeout(() => {
      window.location.href = "/wordpress/my-account";
    }, 3000);
  };

  const proceedWithCheckout = () => {
    if (isBlockBasedCheckout()) {
      wp.data.dispatch('wc/store/checkout').__internalSetProcessing();
    } else {
      jQuery('form.checkout').submit();
    }
  };

  const updateButtonText = () => {
    const checkoutButton = isBlockBasedCheckout()
      ? $('.wc-block-components-checkout-place-order-button')
      : $('button[name="woocommerce_checkout_place_order"]');
    
    if (checkoutButton.text() !== "Verify Identity") {
      checkoutButton.text("Verify Identity");
      if (!isBlockBasedCheckout()) {
        checkoutButton.off("click").on("click", function () {
          const checkout_form = $("form.checkout");
          checkout_form.off("checkout_place_order").on("checkout_place_order", function () {
            if ($("#confirm-order-flag").length === 0) {
              checkout_form.append('<input type="hidden" id="confirm-order-flag" name="confirm-order-flag" value="1">');
            }
            return true;
          });
        });
      }
    }
  };

  const checkCheckoutStatus = () => {
    if (!userData.is_user_logged_in) {
      setInterval(updateButtonText, 500);
      return;
    }
    
    fetch(userData.ajax_url, {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
      },
      body: "action=awareid_check_button_status",
    })
      .then(response => {
        if (!response.ok) throw new Error("Network response was not ok.");
        return response.json();
      })
      .then(data => {
        if (!data.disable_old_button) {
          setInterval(updateButtonText, 500);
        }
      })
      .catch(error => console.error("Error checking button status:", error));
  };

  if (isBlockBasedCheckout()) {
    wp.data.subscribe(() => {
      console.log("Checkout state changed");
      const checkoutStatus = wp.data.select('wc/store/checkout').getCheckoutStatus();
      const isProcessing = wp.data.select('wc/store/checkout').isProcessing();
      const hasError = wp.data.select('wc/store/checkout').hasError();
      console.log(checkoutStatus, isProcessing, hasError);
      if (checkoutStatus === 'before_processing') {
        wp.data.dispatch('wc/store/checkout').__internalSetIdle();
        interceptCheckout();
      }
    });
  } else {
    $(document.body).on("checkout_error", handleCheckoutError);
    $(document).on('click', 'button[name="woocommerce_checkout_place_order"]', interceptCheckout);
  }

  checkCheckoutStatus();
});