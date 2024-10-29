window.onload = () => {
  const script = document.createElement("script");
  script.src = pluginData.knomiWebScript;
  script.onload = () => {
    if (window.Module) {
      console.log("KnomiWeb.js loaded");
    }
  };
  document.head.appendChild(script);
};

const autocapture_frequency_milliseconds = 500;
const autocapture_hold_still_milliseconds = 500;
const endpoint_roi = "/calculateROI";
const endpoint_autocapture = "/autocaptureVideoEncrypted";
const endpoint_analyze = "/analyzeEncrypted";
const payload_username = "webclient";
const licenseForDevelopment = userData.regula_license;
let cameraObj = null;
let payloadObj = null;

let autocaptureUrl = `${userData.awareid_domain}${userData.realm_name}/b2c-sample-web/proxy/autocapture`;
let livenessUrl = userData.add_face_url;
let statelessLivenessUrl = `faceliveness/checkLiveness`;
let verifyUrl = userData.verify_face_url;
let faceRoiUrl = `${userData.awareid_domain}${userData.realm_name}/b2c-sample-web/proxy/calculateRoi`;
let faceAutocaptureUrl = `${userData.awareid_domain}${userData.realm_name}/b2c-sample-web/proxy/autocapture`;
let docValidationUrl = userData.add_document_url;
let roiUrl = `${userData.awareid_domain}${userData.realm_name}/b2c-sample-web/proxy/calculateRoi`;
let documentType = "";
let captureTarget = "FACE";
let isContinuingWorkflow = false;
let regionOfInterest = null;
let autocaptureLoopTimeoutId = null;
let payloadUsername = "";
let isVideoMirrored = false;

let hasError = false;

let isCameraInitialized = false;
let isAutocapturing = false;
let isAutocaptureFinished = false;
let autocaptureMainLoopInterval = null;

let unstableStatus = false;
let isGoodIndicator = false;
let isStopped = false;
let loggedIn = false;
let isTransaction = false;

let brightnessDegree = 1.2;
let isConsecutiveInsufficientLight = false;
let insufficientLightCounter = 0;
let firstGoodFrameTime = 0;

let sessionKey = "";
let enrollmentToken = "";
let reEnrollmentToken = "";
let authToken = "";
let userExists = false;
let checks = [];
let input = 0;
let enrollmentStatus = 2;
let isEnrollment = false;
const cameraModal = document.getElementById("identityVerificationModal");

if (cameraModal) {
  cameraModal.addEventListener("hidden.bs.modal", function (event) {
    stopAllViaModal();
  });
}
const task = {
  NULL: "null",
  CAPTURE: "capture",
};
let currentTask = task.NULL;

// Aspect ratio for document ROI
const aspect = 4.0 / 3.0;

// Fraction of the largest axis image frame to include in document ROI
const coverage = 0.8;

// Enum for Regula Feedback
const ImageQualityCheck = {
  IMAGE_GLARES: 0,
  IMAGE_FOCUS: 1,
  IMAGE_RESOLUTION: 2,
  IMAGE_COLORNESS: 3,
  PERSPECTIVE: 4,
  BOUNDS: 5,
  SCREEN_CAPTURE: 6,
  PORTRAIT: 7,
  HANDWRITTEN: 8,
};

function base64urltoBase64(base64url) {
  let base64 = base64url.replace(/-/g, "+").replace(/_/g, "/");
  const additionalPadding = "=".repeat(4 - (base64.length % 4));
  return base64.concat(additionalPadding);
}

function showSuccess(message) {
  const successSection = document.getElementById("successSection");
  const successText = document.getElementById("successText");
  if (message && message.length > 0) {
    successSection.classList.add("show");
    successSection.classList.remove("hide");
    successText.textContent = message;
  } else {
    successSection.classList.add("hide");
    successSection.classList.remove("show");
    successText.textContent = "";
  }
}

function showError(message) {
  const errorSection = document.getElementById("errorSection");
  const errorText = document.getElementById("errorText");
  if (message && message.length > 0) {
    errorSection.classList.add("show");
    errorSection.classList.remove("hide");
    errorText.textContent = message;
  } else {
    errorSection.classList.add("hide");
    errorSection.classList.remove("show");
    errorText.textContent = "";
  }
  setTimeout(() => {
    errorSection.classList.add("hide");
    errorSection.classList.remove("show");
    errorText.textContent = "";
  }, 5000);
}

function showFeedback(feedback) {
  const feedbackSection = document.getElementById("feedbackSection");
  const feedbackText = document.getElementById("feedbackText");
  if (!feedbackSection || !feedbackText) return;
  if (feedback && feedback.length > 0) {
    feedbackSection.style.display = "block";
    feedbackText.value = feedback;
  } else {
    feedbackText.value = "";
  }
}

function hideFeedback() {
  document.getElementById("feedbackText").value = "";
}

function showResults(message) {
  let msg;
  if (typeof message === "string") {
    msg = message;
  } else if (message && typeof message.message === "string") {
    msg = message.message;
  } else {
    throw new Error(
      "Invalid message format: expected a string or an object with message.message"
    );
  }

  if (msg.includes("Jwt is expired")) {
    msg = "Your session has expired. Please try again!";
  }
  if (msg.includes("MaxAttempts reached")) {
    msg =
      "You have reached the maximum attempts for the authentication. Please try again!";
  }

  showError(msg);
}

function getResultAutocaptureFeedbackList(result) {
  let feedback = [];
  if (Object.prototype.hasOwnProperty.call(result, "frameResults")) {
    // Face

    for (let frameResult of result.frameResults) {
      if (frameResult.feedback.length === 0) continue;
      feedback = feedback.concat(frameResult.feedback);
    }
    return feedback;
  }
  if (Object.prototype.hasOwnProperty.call(result, "results")) {
    // Document
    for (let imageResults of result.results.imageResults) {
      if (imageResults.feedback.length === 0) continue;
      feedback = feedback.concat(imageResults.feedback);
    }
    return feedback;
  }
  return null;
}

function getResultAutocaptureCapturedStatus(result) {
  return result?.results?.captured ?? false;
}

function isResultLive(result) {
  return result?.livenessResult === true ?? false;
}

function isResultSpoof(result) {
  return result?.livenessResult === false ?? false;
}

async function startAutocapture() {
  if (!hasError) {
    updateUserInterface();
    await setUpROI();
    toggleSpinner(false);
  }
}

function centerMainCardInView(mainCard) {
  if (!mainCard) return;

  const elementHeight = mainCard.clientHeight;
  const elementTop = mainCard.offsetTop;
  const viewportHeight = window.innerHeight;

  // Calculate the position to scroll to, to center the element
  const scrollToPosition = elementTop + elementHeight / 2 - viewportHeight / 2;

  window.scrollTo({
    top: scrollToPosition,
    behavior: 'smooth'  // Optional: for smooth scrolling
  });
}

function handleStopAutocapture() {
  if (hasError) return;

  resetAutocaptureState();
  resetUI();
  pauseAndStopCamera();
  clearDocumentContainer();
  resetPreviewAndOverlayVisibility();
}

function resetAutocaptureState() {
  isStopped = true;
  isAutocapturing = false;
  isAutocaptureFinished = true;
  isCameraInitialized = false;
  jwt = "";
}

function resetUI() {
  updateUserInterface();
}

function pauseAndStopCamera() {
  cameraObj.Pause();
  showFeedback("");
}

function stopAllViaModal() {
  cameraObj.Stop();
  clearTimeout(autocaptureMainLoopInterval);
  isStopped = true;
  isAutocapturing = false;
  isAutocaptureFinished = true;
  isCameraInitialized = false;
  jwt = "";
  clearDocumentContainer();
  hideCamera();
  const faceImageButton = document.getElementById("faceImage");
  faceImageButton.classList.remove("d-none");
  const previewSection = document.getElementById("previewSection");
  setVisibility(previewSection, "none", "none");
}

function clearDocumentContainer() {
  document.getElementById("documentContainer").innerHTML = "";
}

function resetPreviewAndOverlayVisibility() {
  setVisibility("#previewWindow", "visible");
  setVisibility("#ovalOverlay", "visible");
  document.querySelector("#documentContainer").classList.remove("d-none");
}

function setVisibility(selector, visibility) {
  const element = document.querySelector(selector);
  if (element) {
    element.style.visibility = visibility;
  }
}

function handleFlipButton() {
  const videoElement = document.getElementById("previewWindow");
  if (videoElement.classList.contains("mirrorVideo")) {
    mirrorVideo(false);
  } else {
    mirrorVideo(true);
  }
}

function handleEnrollmentButton() {
  captureTarget = "FACE";
  roiUrl = faceRoiUrl;
  autocaptureUrl = faceAutocaptureUrl;
  isContinuingWorkflow = false;
  isEnrollment = true;
  enrollmentStatus = 1;
  handleCloseError();

  const mailFormat = /^\w+([\.-]?\w+)*@\w+([\.-]?\w+)*(\.\w{2,})+$/;
  userExists = false;

  payloadUsername = userData.email;

  if (!payloadUsername.match(mailFormat)) {
    showError("Invalid email address.");
    return;
  }

  payloadObj.SetPropertyString(
    Module.PayloadProperty.USERNAME,
    payloadUsername
  );
  cameraObj.SetPropertyString(
    Module.CameraProperty.CAPTURE_TARGET,
    captureTarget
  );
  toggleSpinner(true);
  const faceElement = document.querySelector("#previewWindow");
  const overlayElement = document.querySelector("#ovalOverlay");
  faceElement.style.visibility = "visible";
  overlayElement.style.visibility = "visible";
  overlayElement.style.display = "block"

  startEnrollment().then(function () {
    if (userExists) {
      startAuthentication();
    }

    currentTask = task.CAPTURE;
    if (isCameraInitialized) {
      startAutocapture();
    } else {
      initializeCamera();
    }
  });
}

function clearRegionOfInterest() {
  const canvas = document.getElementById("ovalOverlay");
  const ctx = canvas.getContext("2d");
  ctx.clearRect(0, 0, canvas.width, canvas.height);
}

async function stopTracks() {
  // Get camera tag
  const video = document.getElementById("previewWindow");
  let currentStream = null;
  if ("srcObject" in video) {
    currentStream = video.srcObject;
    if (currentStream) {
      currentStream.getTracks().forEach((track) => track.stop());
    }
  }
}

function stopTask() {
  if (hasError) {
    return;
  }
  isAutocapturing = false;
  updateUserInterface();
  cameraObj.Stop();
  isCameraInitialized = false;
  showFeedback("");
  clearTimeout(autocaptureLoopTimeoutId);
}

async function validateDocument(strImageFront, strImageBack) {
  if (strImageFront == null) {
    throw "null image passed to validateDocumentRegula";
  }

  let payload = {
    body: {
      enrollmentToken: enrollmentToken,
      documentsInfo: {
        documentImage: [
          { image: strImageFront, lightingScheme: 6, format: ".jpeg" },
        ],
        processParam: {
          "alreadyCropped": false,
        }
      },
    },
    nonce: userData.nonce,
    jwt: `${jwt}`,
    targetUrl: "b2c/onboarding/enrollment/addDocumentOCR",
  };
  if (strImageBack != null) {
    payload.documentsInfo.documentImage.push({
      image: strImageBack,
      lightingScheme: 6,
      format: ".jpeg",
    });
  }
  let videoSpinnerParent = document.querySelector("#loadingSpinnerVideoParent");
  let successAnimation = document.querySelector("#successAnimation");
  let failAnimation = document.querySelector("#failAnimation");
  videoSpinnerParent.classList.remove("d-none");
  let videoSpinner = document.querySelector("#loadingSpinnerVideo");
  videoSpinner.classList.remove("d-none");

  showFeedback("Validating document...");
  try {
    const validationResult = await postPayloadFetch(docValidationUrl, payload);
    videoSpinner.classList.add("d-none");
    if (
      validationResult.matchResult &&
      validationResult.documentVerificationResult
    ) {
      successAnimation.classList.remove("d-none");
      loggedIn = true;
      checkButtonStatus();
      var currentPageUrl = window.location.href;
      // Check if current page is the checkout page

      setTimeout(() => {
        successAnimation.classList.add("d-none");
        videoSpinnerParent.classList.add("d-none");
        if (currentPageUrl.includes(userData.checkout_url)) {
          if ((isBlockBasedCheckout())) {
            wp.data.dispatch('wc/store/checkout').__internalSetProcessing();
          } else {
            jQuery("#confirm-order-flag").val("");
            jQuery("#place_order").trigger("click");
          }
        }
        updateUserInterface();
        var myModalEl = document.getElementById("identityVerificationModal");
        var modal = bootstrap.Modal.getInstance(myModalEl);
        modal.hide();
      }, 3000);
    } else if (
      validationResult.matchResult &&
      !validationResult.documentVerificationResult
    ) {
      failAnimation.classList.remove("d-none");
      showResults("Document could not be verified.");
      setTimeout(() => {
        failAnimation.classList.add("d-none");
        videoSpinnerParent.classList.add("d-none");
        updateUserInterface();
        var myModalEl = document.getElementById("identityVerificationModal");
        var modal = bootstrap.Modal.getInstance(myModalEl);
        modal.hide();
      }, 3000);
    } else if (
      !validationResult.matchResult &&
      validationResult.documentVerificationResult
    ) {
      failAnimation.classList.remove("d-none");
      showResults("Face match failed.");
      setTimeout(() => {
        failAnimation.classList.add("d-none");
        videoSpinnerParent.classList.add("d-none");
        updateUserInterface();
        var myModalEl = document.getElementById("identityVerificationModal");
        var modal = bootstrap.Modal.getInstance(myModalEl);
        modal.hide();
      }, 3000);
    } else if (
      !validationResult.matchResult &&
      !validationResult.documentVerificationResult
    ) {
      failAnimation.classList.remove("d-none");
      showResults("Face match failed.");
      setTimeout(() => {
        failAnimation.classList.add("d-none");
        videoSpinnerParent.classList.add("d-none");
        updateUserInterface();
        var myModalEl = document.getElementById("identityVerificationModal");
        var modal = bootstrap.Modal.getInstance(myModalEl);
        modal.hide();
      }, 3000);
    } else {
      failAnimation.classList.remove("d-none");
      showResults("Poor image quality. Please try again.");
      setTimeout(() => {
        failAnimation.classList.add("d-none");
        videoSpinnerParent.classList.add("d-none");
        updateUserInterface();
        var myModalEl = document.getElementById("identityVerificationModal");
        var modal = bootstrap.Modal.getInstance(myModalEl);
        modal.hide();
      }, 3000);
    }
  } catch (error) {
    failAnimation.classList.remove("d-none");
    if (error.message != null) {
      showResults(error.message);
    } else {
      showResults("Server Error Validating Document");
    }
    loggedIn = false;
    setTimeout(() => {
      failAnimation.classList.add("d-none");
      videoSpinnerParent.classList.add("d-none");
      updateUserInterface();
      var myModalEl = document.getElementById("identityVerificationModal");
      var modal = bootstrap.Modal.getInstance(myModalEl);
      modal.hide();
    }, 3000);
  }

  showFeedback("");
  isAutocapturing = false;
}

function hasProp(obj, prop) {
  let props = prop.split(".");
  const length = props.length;
  if (length === 0)
    return false;
  for (let i = 0; i < length; i++) {
    const key = props[i];
    if (!Object.prototype.hasOwnProperty.call(obj, key))
      return false;
    obj = obj[key];
  }
  return true;
}

function mirrorVideo(mirror) {
  const videoElement = document.getElementById("previewWindow");
  videoElement.classList.toggle("mirrorVideo", mirror);
  if (mirror) {
    isVideoMirrored = true;
  } else {
    isVideoMirrored = false;
  }
}

async function handleAuthenticationButton() {
  captureTarget = "FACE";
  roiUrl = faceRoiUrl;
  autocaptureUrl = faceAutocaptureUrl;
  isContinuingWorkflow = false;
  enrollmentStatus = 1;
  isEnrollment = false;
  handleCloseError();
  const mailFormat = /^\w+([\.-]?\w+)*@\w+([\.-]?\w+)*(\.\w{2,})+$/;
  payloadUsername = userData.email;
  if (!payloadUsername.match(mailFormat)) {
    showError("Invalid email address.");
    return;
  }
  const faceElement = document.querySelector("#previewWindow");
  const overlayElement = document.querySelector("#ovalOverlay");
  faceElement.style.visibility = "visible";
  overlayElement.style.visibility = "visible";

  payloadObj.SetPropertyString(
    Module.PayloadProperty.USERNAME,
    payloadUsername
  );
  cameraObj.SetPropertyString(
    Module.CameraProperty.CAPTURE_TARGET,
    captureTarget
  );

  toggleSpinner(true);
  startAuthentication().then(function () {
    if (isCameraInitialized) {
      startAutocapture();
    } else {
      initializeCamera();
    }
    toggleSpinner(false);
  });
}

function handleTransactionAuthentication() {
  isEnrollment = false;
  startAuthentication().then(function () {
    currentTask = task.CAPTURE;
    if (isCameraInitialized) {
      startAutocapture();
    } else {
      initializeCamera();
    }
    toggleSpinner(false);
  });
}

function handleCloseError() {
  let errorSection = document.getElementById("errorSection");
  errorSection.classList.remove("show");
}

async function autocaptureMainLoop() {
  if (hasError) {
    clearTimeout(autocaptureMainLoopInterval);
    updateUserInterface();
    return;
  }

  if (isContinuingWorkflow) {
    isAutocaptureFinished = false;
    pauseAndStopCamera();
    showFeedback("Moving to Document Capture");
    clearTimeout(autocaptureMainLoopInterval);
    updateUserInterface();
    return;
  }

  if (isAutocaptureFinished || !isAutocapturing) {
    isAutocapturing = false;
    isAutocaptureFinished = false;
    showFeedback("Capture Stopped");
    clearTimeout(autocaptureMainLoopInterval);
    updateUserInterface();
    return;
  }

  let loopStartTime = new Date().getTime();

  try {
    // Get frames and send to autocapture analysis server
    await collectCameraFrames();

    // Get the payload that contains the camera image
    const autocapturePayload = payloadObj.GetAutocapturePayload(cameraObj);
    if (autocapturePayload !== "") {
      const payload = JSON.parse(autocapturePayload);
      payload.jwt = jwt;
      // Send the payload for autocapture analyzsis
      const autoCaptureAnalysisResult = await postPayloadFetch(
        autocaptureUrl,
        payload
      );

      if ("error" in autoCaptureAnalysisResult) {
        cameraObj.Pause();
        showResults(autoCaptureAnalysisResult.error.description);
        insufficientLightCounter = 0;
        isAutocapturing = false;
        updateUserInterface();
      }
      const feedbackList = getResultAutocaptureFeedbackList(
        autoCaptureAnalysisResult
      );
      if (feedbackList != null) {
        if (feedbackList.indexOf("INSUFFICIENT_LIGHTING") !== -1) {
          insufficientLightCounter++;
        } else {
          insufficientLightCounter = 0;
        }
      }

      let hasCapturableImage = getResultAutocaptureCapturedStatus(
        autoCaptureAnalysisResult
      );

      const videoElement = document.getElementById("previewWindow");

      if (hasCapturableImage) {
        showFeedback("");
        showGoodIndicator();
        if (firstGoodFrameTime > 0) {
          if (
            loopStartTime - firstGoodFrameTime >
            autocapture_hold_still_milliseconds
          ) {
            await checkForLiveness();
          }
        } else {
          firstGoodFrameTime = loopStartTime;
        }
      } else {
        firstGoodFrameTime = 0;
        showBadIndicator();
        if (Array.isArray(feedbackList) && feedbackList.length > 0) {
          const validFeedback =
            feedbackList.find((item) => !item.includes("INVALID_POSE")) ||
            feedbackList[0];
          showFeedback(modifyString(JSON.stringify(validFeedback)));
        }
      }
    }
    if (isAutocapturing) {
      const delay = Math.max(
        0,
        autocapture_frequency_milliseconds - loopStartTime
      );
      autocaptureLoopTimeoutId = setTimeout(autocaptureMainLoop, delay);
    } else {
      updateUserInterface();
    }
  } catch (error) {
    cameraObj.Pause();
    showResults(error);
    insufficientLightCounter = 0;
    isAutocapturing = false;
    updateUserInterface();
  }
}

function initializeCamera() {
  if (cameraObj) {
    if (hasError) {
      cameraObj = null;
      createCamera();
    }
    if (!hasError) {
      cameraObj.Initialize();
    }
  }
}

function pauseCamera() {
  if (cameraObj) {
    cameraObj.Pause();
  }
}

function resumeCamera() {
  if (cameraObj) {
    cameraObj.Play();
  }
}
function toggleSpinner(enabled) {
  let spinner = document.getElementById("loadingSpinner");
  if (enabled) {
    spinner.classList.add("d-flex");
    spinner.disabled = false;
    spinner.style.display = "";
  } else {
    spinner.classList.remove("d-flex");
    spinner.disabled = true;
    spinner.style.display = "none";
  }
}

function toggleStopAutocaptureButton(enabled) {
  let stopAutocaptureButton = document.getElementById("stopAutocaptureBtn");
  if (!stopAutocaptureButton) return;
  if (enabled) {
    stopAutocaptureButton.disabled = false;
    stopAutocaptureButton.style.display = "none";
  } else {
    stopAutocaptureButton.disabled = true;
    stopAutocaptureButton.style.display = "none";
  }
}

function showCamera() {
  let previewSection = document.getElementById("previewSection");
  previewSection.style.visibility = "visible";
}

function hideCamera() {
  let previewSection = document.getElementById("previewSection");
  previewSection.style.visibility = "hidden";
}

async function postPayloadFetch(url, body) {
  // Prepare the data to send to WordPress, including the original request details

  // Send the request to your WordPress server
  let wordpressResponse = await fetch(url, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      ...(url.includes("my_namespace/v1/")
        ? { "X-WP-Nonce": userData.nonce }
        : {}),
    },
    body: JSON.stringify(body),
  });

  // Handle the response from WordPress
  if (wordpressResponse.ok) {
    const data = await wordpressResponse.json();
    if (data) {
      return data;
    }
  } else {
    const error_text = await wordpressResponse.text();
    if (error_text !== "") {
      throw new Error(error_text);
    }
    throw new Error("Received invalid response from WordPress server");
  }
}

async function validateSession() {
  let validateUrl = `${userData.awareid_domain}${userData.realm_name}/b2c-sample-web/proxy/validateSession`;
  let body = {
    sessionToken: sessionKey,
    jwt: `${jwt}`,
  };
  try {
    response = await postPayloadFetch(validateUrl, body);
  } catch (e) {
    throw new Error(e.toString());
  }

  try {
    if (response) {
      const data = response;
      jwt = data.accessToken;
    } // If the request was not successful, throw an error with the appropriate message
    else {
      const error_text = await response.text();
      if (error_text != "") {
        throw new Error(error_text);
      }
      throw new Error("Received invalid response from the server");
    }
  } catch (e) {
    throw e;
  }
}

async function validateSignature(data) {
  try {
    let delimitedData = new URLSearchParams(new URL(data).search);
    let response = fetch(userData.rest_url, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-WP-Nonce": userData.nonce, // This will ensure nonce check for security
      },
      body: JSON.stringify({ data: delimitedData.get("data") }),
    });
  } catch (error) {
    console.error("Fetch error:", error);
  }
}

async function triggerInitiate() {
  // URL for the enroll endpoint
  let enrollUrl = `${userData.awareid_domain}${userData.realm_name}/b2c-sample-web/proxy/triggerEnroll`;
  // Request body with username, email, and notification options
  let body = {
    username: `${payloadUsername}`,
    email: `${payloadUsername}`,
    notifyOptions: { notifyByEmail: false },
  };

  // Send the POST request and handle any errors
  try {
    response = await postPayloadFetch(enrollUrl, body);
  } catch {
    throw new Error("Error in triggering authentication.");
  }

  // If the request was successful, extract the session token from the response data
  try {
    if (response) {
      sessionKey = response.sessionToken;
      jwt = response.jwtToken;
      validateSignature(response.sessionCallbackURL);
      validateSession();
    }
    // If the request was not successful, throw an error with the appropriate message
    else {
      throw new Error("Received invalid response from the server");
    }
  } catch (e) {
    throw e;
  }
}

async function triggerAuthenticate() {
  // URL for the authenticate endpoint
  let authUrl = `${userData.awareid_domain}${userData.realm_name}/b2c-sample-web/proxy/triggerAuth`;
  // Request body with username, email, phone number, and notification options
  let body = {
    username: `${payloadUsername}`,
    email: `${payloadUsername}`,
    phoneNumber: "",
    notifyOptions: { notifyByEmail: false },
  };

  try {
    response = await postPayloadFetch(authUrl, body);
  } catch {
    throw new Error("Error in triggering authentication.");
  }

  // If the request was successful, extract the session token from the response data
  try {
    if (response) {
      sessionKey = response.sessionToken;
      validateSignature(response.sessionCallbackURL);
      validateSession();
    }
    // If the request was not successful, throw an error with the appropriate message
    else {
      toggleSpinner(false);
      throw new Error("Received invalid response from the server");
    }
  } catch (e) {
    throw e;
  }
}

async function startEnrollment() {
  await triggerInitiate();
  let enrollUrl = `${userData.awareid_domain}${userData.realm_name}/b2c-sample-web/proxy/doEnroll`;
  let body = { sessionToken: `${sessionKey}`, jwt: `${jwt}` };

  let res = await postPayloadFetch(enrollUrl, body);
  if (res) {
    if (!res.userExistsAlready) {
      enrollmentToken = res.enrollmentToken;
    } else {
      reEnrollmentToken = res.reEnrollmentToken;
      userExists = true;
    }
    checks = res.requiredChecks;
    if (checks.includes("addDevice")) {
      addDevice();
    }
  }
}

async function startAuthentication() {
  await triggerAuthenticate();
  let authUrl = `${userData.awareid_domain}${userData.realm_name}/b2c-sample-web/proxy/doAuth`;
  let body = { sessionToken: `${sessionKey}`, jwt: `${jwt}` };
  let res = await postPayloadFetch(authUrl, body);
  if (res) {
    authToken = res.authToken;
    checks = res.requiredChecks;
  }
}

async function addDevice() {
  let addDeviceUrl = `${userData.awareid_domain}${userData.realm_name}/b2c-sample-web/proxy/addDevice`;
  let body = {
    enrollmentToken: `${enrollmentToken}`,
    deviceId: "webapp01",
    pushId:
      "cyWjoP0BRUsLkWFznjNz8f:APA91bGhwZd8AqQG9HDDlQAnOostFuk6GZd6JIKK5O09qwzHzj9Ixo0x_ATsGoEMe3RctQ09_zh0opVR7ELqtjOGWYf4w0mUWEQk47rFhOZlruQgSxW6QAlF530wkYQe1krt4NTDQmm2",
    publicKey:
      "MFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAEZt5fpfqpdlpILVZ+bJymkSxwsWVdKu1OdV0Dx0s3gJQ0OrxaoSZ9ctMINt1Ak0n1PK8L6m4xnkKhT6ILSQeIQYgG+y8rPOxvGmX+wkkqXYQiPpv8DsBdNRLXSh+KtQ+v",
    jwt: `${jwt}`,
  };
  postPayloadFetch(addDeviceUrl, body);
}

function collectCameraFrames() {
  if (insufficientLightCounter !== 0 && insufficientLightCounter % 3 === 0) {
    brightnessDegree = brightnessDegree + 0.4;
    cameraObj.SetPropertyDouble(
      Module.CameraProperty.BRIGHTNESS,
      brightnessDegree
    );
  }

  if (insufficientLightCounter > 10) {
    throw new Error("Brightness has been increased too many times...");
  }

  cameraObj.CollectFrames();
}
async function checkForLiveness() {
  pauseCameraAndSetWorkflow();
  const payload = getPayload();
  let res = buildResponseObject(payload);

  if (checks.includes("addFace")) {
    res = updateUserIfExists(payload, res);
  } else if (checks.includes("verifyFace")) {
    res = verifyUserFace(payload, res);
  }

  const result = await postPayloadFetch(determineUrl(res), res);
  handleLivenessResult(result);
}

function pauseCameraAndSetWorkflow() {
  cameraObj.Pause();
  const charlieModule = `CHARLIE${userData.security_level}`;
  const hotelModule = `HOTEL${userData.security_level}`;
  const workflow = cameraObj.IsMobileDevice()
    ? Module[charlieModule]
    : Module[hotelModule];
  payloadObj.SetPropertyString(Module.PayloadProperty.WORKFLOW, workflow);
}

function getPayload() {
  return payloadObj.GetAnalyzePayload(cameraObj);
}

function buildResponseObject(payload) {
  return {
    enrollmentToken: enrollmentToken,
    faceLivenessData: JSON.parse(payload),
    jwt: `${jwt}`,
  };
}

function updateUserIfExists(payload, res) {
  if (!userExists) {
    return {
      body: {
        ...res,
        enrollmentToken: enrollmentToken,
        faceLivenessData: JSON.parse(payload),
      },
      nonce: userData.nonce,
      targetUrl: "b2c/onboarding/enrollment/addFace",
      jwt: jwt,
    };
  } else {
    return {
      reEnrollmentToken: reEnrollmentToken,
      faceLivenessData: JSON.parse(payload),
    };
  }
}

function verifyUserFace(payload, res) {
  return {
    body: { authToken: authToken, faceLivenessData: JSON.parse(payload) },
    jwt: jwt,
    nonce: userData.nonce,
    targetUrl: "b2c/onboarding/authentication/verifyFace",
  };
}

function determineUrl(res) {
  return res.body.enrollmentToken ? livenessUrl : verifyUrl;
}

async function handleLivenessResult(result) {
  if (isResultLive(result)) {
    processLiveResult(result);
  } else if (isResultSpoof(result)) {
    processSpoofResult(result);
  } else {
    processFailedResult(result);
  }
}

function processLiveResult(result) {
  isAutocaptureFinished = true;
  showFeedback("");
  if ("matchResult" in result) {
    handleMatchResult(result);
  } else if ("enrollmentStatus" in result) {
    handleEnrollmentStatus(result);
  }
}

function handleMatchResult(result) {
  if (result.authStatus === 0) {
    maxAttemptsReached();
    return;
  }
  if (result.matchResult) {
    checkButtonStatus();
    loggedIn = true;
  } else {
    matchFailed();
  }
}

function handleEnrollmentStatus(result) {
  if (result.enrollmentStatus == 2) {
    var currentPageUrl = window.location.href;
    // Check if current page is the checkout page
    if (currentPageUrl.includes(userData.checkout_url)) {
      if (isBlockBasedCheckout()) {
        wp.data.dispatch('wc/store/checkout').__internalSetProcessing();
      } else {
        jQuery("#confirm-order-flag").val("");
        jQuery("#place_order").trigger("click");
      }
    }
    loggedIn = true;
    checkButtonStatus();
  } else if (result.enrollmentStatus == 1 && checks.includes("addDocument")) {
    prepareForDocumentCapture();
  }
}

function processSpoofResult(result) {
  showFeedback("FaceLiveness Failed");
  isAutocaptureFinished = false;
  if (result.authStatus == 0) {
    maxAttemptsReached();
    return;
  }
  cameraObj.Play();
}

function processFailedResult(result) {
  showFeedback("FaceLiveness Failed");
  if (result.authStatus == 0) {
    maxAttemptsReached();
    return;
  }
  isAutocaptureFinished = false;
  resumeCamera();
}

function maxAttemptsReached() {
  cameraObj.Pause();
  showResults("MaxAttempts reached");
  insufficientLightCounter = 0;
  isAutocapturing = false;
  updateUserInterface();
  enrollmentStatus = 0;
}

function matchFailed() {
  showFeedback("Face Match Failed!");
  showBadIndicator();
  isAutocaptureFinished = false;
  isStableCaptured = false;
  resumeCamera();
}

function prepareForDocumentCapture() {
  isAutocaptureFinished = true;
  hasCapturableImage = false;
  isContinuingWorkflow = true;
  isStopped = true;
  pauseCamera();
  cameraObj.ClearImages();
  if (autocaptureMainLoopInterval) {
    clearTimeout(autocaptureMainLoopInterval);
  }
  setTimeout(changeCaptureTargetNew, 3000);
  resumeCamera();
}

/* 

UI Functions

*/

function registerControls() {
  let stopAutocaptureBtn = document.getElementById("stopAutocaptureBtn");
  let captureBtn = document.getElementById("captureBtn");
  let closeError = document.getElementById("closeError");
  if (captureBtn && closeError && stopAutocaptureBtn) {
    closeError.addEventListener("click", handleCloseError);
    stopAutocaptureBtn.addEventListener("click", handleStopAutocapture);
    captureBtn.addEventListener("click", handleEnrollmentButton);
  }
}
function updateUserInterface() {
  resizeContent();
  togglePreviewVisibility();
  updateLoginAndEnrollmentUI();
  updateButtonStates();
}

function resizeContent() {
  if (!cameraObj) return;

  const previewSection = document.getElementById("previewSection");
  const contentSections = document.querySelectorAll(".content");
  const contentVideoSections = document.querySelectorAll(".content video");
  const feedbackSection = document.getElementById("feedbackSection");

  const width = cameraObj.GetPreviewWidth().toString();
  const height = cameraObj.GetPreviewHeight().toString();

  setAttributes(previewSection, width, height);
  contentSections.forEach((section) => setAttributes(section, width, height));
  contentVideoSections.forEach((section) =>
    setAttributes(section, width, height)
  );
}

function setAttributes(element, width, height) {
  if (element) {
    element.setAttribute("width", width);
    element.setAttribute("height", height);
  }
}

function togglePreviewVisibility() {
  const previewSection = document.getElementById("previewSection");
  const elementsToHide = document.querySelectorAll(
    "#configSection_payload_username > *, #memberStatus > *, #scenario-choice > *"
  );

  if (isCameraInitialized && isAutocapturing) {
    elementsToHide.forEach((el) => (el.style.display = "none"));
    setVisibility(previewSection, "visible", "block");
  } else {
    setVisibility(previewSection, "none", "none");
    elementsToHide.forEach((el) => (el.style.display = ""));
  }
}

function setVisibility(element, visibility, display) {
  if (element) {
    element.style.visibility = visibility;
    element.style.display = display;
  }
}

function updateLoginAndEnrollmentUI() {
  if (!loggedIn) return;

  const loginView = document.getElementById("loginView");
  loginView.removeAttribute("hidden");
  const loginTextBox = document.getElementById("loginTextBox");
  const previewSection = document.getElementById("previewSection");

  if (!isTransaction) {
    if (!isEnrollment) {
      loginTextBox.textContent = "Identity Verified!";
    } else {
      loginTextBox.textContent = "Identity Verified!";
      setTimeout(() => {
        var myModalEl = document.getElementById("identityVerificationModal");
        var modal = bootstrap.Modal.getInstance(myModalEl);
        modal.hide();
      }, 3000);
    }
  }
}

function performEnrollmentUIReset() {
  setTimeout(() => {
    resetEnrollmentUI();
  }, 3000);
}

function resetEnrollmentUI() {
  document.getElementById("loginView").hidden = true;
}

function resetElementsDisplay(selector, minHeight = "") {
  document.querySelectorAll(selector).forEach((el) => {
    el.style.display = "";
    if (minHeight) el.style.minHeight = minHeight;
  });
}

function updateButtonStates() {
  if (isCameraInitialized) {
    toggleStopAutocaptureButton(isAutocapturing);
  } else {
    toggleStopAutocaptureButton(false);
  }
}

function disableTaskButtons() {
  let taskButtons = document.querySelectorAll(".task");
  taskButtons.forEach((button) => (button.disabled = true));
}

function enableTaskButtons() {
  let taskButtons = document.querySelectorAll(".task");
  taskButtons.forEach((button) => (button.disabled = false));
}

/*

ROI Functions

*/

async function setUpROI() {
  const videoElement = document.getElementById("previewWindow");
  if (!videoElement.videoWidth) {
    // Don't have width yet, wait and trying again
    setTimeout(setUpROI, 500);
    return;
  }

  await requestROI();
  isAutocapturing = true;
  isAutocaptureFinished = false;

  if (autocaptureLoopTimeoutId) {
    clearTimeout(autocaptureLoopTimeoutId);
  }
  showFeedback("");
  updateUserInterface();
  if (cameraObj.IsMobileDevice) {
    mirrorVideo(true);
  }
  cameraObj.Play();
  showBadIndicator();

  // Start autocaptureLoop via setTimeout to allow the browser to setup the camera.
  autocaptureLoopTimeoutId = setTimeout(
    autocaptureMainLoop,
    autocapture_frequency_milliseconds
  );
}

async function requestROI() {
  try {
    let roiPayload = payloadObj.GetRegionOfInterestPayload(cameraObj);
    let payloadObject = JSON.parse(roiPayload);

    // Add the JWT
    payloadObject.jwt = jwt;

    regionOfInterest = await postPayloadFetch(roiUrl, payloadObject);
    cameraObj.SetRoi(regionOfInterest.x, regionOfInterest.y, regionOfInterest.width, regionOfInterest.height);
    showBadIndicator();
    updateUserInterface();
  } catch (error) {
    showError(
      "Received error trying to get region of interest. See log for details."
    );
    hideCamera();
    toggleStopAutocaptureButton(false);
    hasError = true;
  }
}

/* 

Camera UI Functions

*/

function drawFaceRegionOfInterest(roiColor) {
  // Note: Width of the displayed ROI is based on just the height, not on
  // the specified width
  if (!regionOfInterest) return;
  const mainBox = document.getElementById("loginBox");
  const videoElement = document.getElementById("previewWindow");
  const mainTitle = document.getElementById("main-title");
  const feedbackSection = document.getElementById("feedbackSection");

  const minHeight =
    videoElement.clientHeight +
    mainTitle.offsetHeight +
    feedbackSection.offsetHeight;
  mainBox.style.minHeight = `${minHeight}px`;

  const lineWidth = 2;
  const previewWidth = videoElement.clientWidth;
  const previewHeight = videoElement.clientHeight;
  const scale = videoElement.clientWidth / videoElement.videoWidth;

  if (scale === 0) return; // Video window not visible yet

  const roiX = regionOfInterest.x * scale;
  const roiY = regionOfInterest.y * scale;
  const roiWidth = regionOfInterest.width * scale;
  const roiHeight = regionOfInterest.height * scale;
  const roiRadius = (roiHeight * 0.75) / 2;

  const centerX = roiX + roiWidth / 2;
  const centerY = roiY + roiHeight / 2;

  // Adjust the size of the silhouette based on the ROI
  const silhouetteSize = Math.min(roiWidth, roiHeight) * 0.5; // Example: 50% of the smaller ROI dimension

  // Get the silhouette image element
  const silhouetteImage = document.getElementById('faceImage');

  // Position and size the silhouette image
  silhouetteImage.style.position = 'absolute';
  silhouetteImage.style.left = `${centerX - silhouetteSize / 2}px`; // Center the image horizontally
  silhouetteImage.style.top = `${centerY - silhouetteSize / 2}px`; // Center the image vertically
  silhouetteImage.style.width = `${silhouetteSize}px`;
  silhouetteImage.style.height = 'auto'; // Maintain aspect ratio

  // Setup canvas
  const canvas = document.getElementById("ovalOverlay");
  canvas.width = previewWidth;
  canvas.height = previewHeight;

  const ctx = canvas.getContext("2d");
  ctx.lineWidth = lineWidth;

  // Draw black box, 50% transparent over canvas
  ctx.clearRect(0, 0, previewWidth, previewHeight);
  ctx.fillStyle = "rgba(0, 0, 0, 0.5)";
  ctx.fillRect(0, 0, previewWidth, previewHeight);
  ctx.globalCompositeOperation = "destination-out";

  // Top arc
  ctx.beginPath();
  ctx.fillStyle = "rgba(0,0,0,1)";
  ctx.arc(
    roiX + roiWidth / 2,
    roiY + roiRadius,
    roiRadius,
    Math.PI,
    2 * Math.PI
  );
  ctx.fill();

  // Bottom arc
  ctx.beginPath();
  ctx.fillStyle = "rgba(0,0,0,1)";
  ctx.arc(
    roiX + roiWidth / 2,
    roiY + roiHeight - roiRadius,
    roiRadius,
    0,
    Math.PI
  );
  ctx.fill();

  // Offset - fill the blanks
  const offset_x = 1;
  const offset_y = 2;
  ctx.clearRect(
    roiX + roiWidth / 2 - roiRadius - offset_x,
    roiY + roiRadius - offset_x,
    2 * roiRadius + offset_x,
    roiHeight - 2 * roiRadius + offset_y
  );
  ctx.globalCompositeOperation = "source-over";

  // Top arc
  ctx.beginPath();
  ctx.arc(
    roiX + roiWidth / 2,
    roiY + roiRadius,
    roiRadius,
    Math.PI,
    2 * Math.PI
  );
  ctx.strokeStyle = roiColor;
  ctx.stroke();

  // Bottom arc
  ctx.beginPath();
  ctx.arc(
    roiX + roiWidth / 2,
    roiY + roiHeight - roiRadius,
    roiRadius,
    0,
    Math.PI
  );
  ctx.strokeStyle = roiColor;
  ctx.stroke();

  // Left side
  ctx.beginPath();
  ctx.moveTo(roiX + roiWidth / 2 - roiRadius, roiY + roiRadius);
  ctx.lineTo(roiX + roiWidth / 2 - roiRadius, roiY + roiHeight - roiRadius);
  ctx.strokeStyle = roiColor;
  ctx.stroke();

  // Right side
  ctx.beginPath();
  ctx.moveTo(roiX + roiWidth / 2 + roiRadius, roiY + roiRadius);
  ctx.lineTo(roiX + roiWidth / 2 + roiRadius, roiY + roiHeight - roiRadius);
  ctx.strokeStyle = roiColor;
  ctx.stroke();
}

function clearRegionOfInterest() {
  const canvas = document.getElementById("ovalOverlay");
  const ctx = canvas.getContext("2d");
  ctx.clearRect(0, 0, canvas.width, canvas.height);
}

function showGoodIndicator() {
  if (captureTarget === "FACE") drawFaceRegionOfInterest("green");
}

function showBadIndicator() {
  if (captureTarget === "FACE") drawFaceRegionOfInterest("red");
}

function modifyString(s) {
  s = s.replace(/_/g, " ");
  s = s.slice(1, -1);
  s = s
    .toLowerCase()
    .split(" ")
    .map((s) => s.charAt(0).toUpperCase() + s.substring(1))
    .join(" ");
  switch (s) {
    case "Face Too Far":
      return "Move Face Closer";
    case "Face Too Close":
      return "Move Face Back";
    case "Face Too Low":
      return "Move Face Up";
    case "Face Too High":
      return "Move Face Down";
    case "Face On Left":
      return "Move Face Right";
    case "Face On Right":
      return "Move Face Left";
    case "Right Eye Closed":
      return "Open Right Eye";
    case "Left Eye Closed":
      return "Open Left Eye";
    case "Dark Glasses":
      return "Remove Glasses";
    default:
      return s;
  }
}

/*

Knomi Web SDK Functions

*/

function cameraFinishedInitializingSuccess() {
  isCameraInitialized = true;
  startAutocapture();
}

function cameraFinishedInitializingFailure(error) {
  isCameraInitialized = false;
  showError(error);
  hideCamera();
  toggleStopAutocaptureButton(false);
  hasError = true;
  updateUserInterface();
}

async function createCamera() {
  // Create camera
  hasError = false;
  try {
    payloadObj = new Module.Payload();
    cameraObj = new Module.Camera();
    cameraObj.SetPropertyString(
      Module.CameraProperty.FINISHED_INITIALIZING_SUCCESS_FN,
      "cameraFinishedInitializingSuccess"
    );
    cameraObj.SetPropertyString(
      Module.CameraProperty.FINISHED_INITIALIZING_FAILURE_FN,
      "cameraFinishedInitializingFailure"
    );
    cameraObj.SetPropertyInt(Module.CameraProperty.ORIENTATION,
      cameraObj.IsMobileDevice()
        ? Module.CameraOrientation.PORTRAIT.value
        : Module.CameraOrientation.LANDSCAPE.value);
    cameraObj.SetPropertyString(
      Module.CameraProperty.CAMERA_TAG_ID,
      "previewWindow"
    );
    payloadObj.SetEncryptionKey(cameraObj, userData.public_key);
    // cameraObj.SetPropertyString(Module.CameraProperty.PROCESSOR_URL, "https://awareid-wasm.web.app/jordan-wasm/KnomiWebProcessor.js");
  } catch (e) {
    if (e.message === Module.TRIAL_EXPIRATION_PASSED) {
      alert("Trial expiration has passed. See log for details.");
      console.log(
        "The software tryout period has expired. Please Contact Aware, Inc. at support@aware.com."
      );
    } else if (e.message === Module.PLATFORM_NOT_SUPPORTED) {
      alert("Platform not supported. See log for details.");
      console.log(
        "Platform not supported. KnomiWeb is configured for emulator detection which is only supported on mobile browsers."
      );
    } else {
      console.log(e);
    }
    return;
  }
}

// This is called by the KnomiWeb WASM Library when it is loaded
function KnomiWebAllReady() {
  if (document.readyState === "complete") {
    // Everything loaded, start the app
    initApp();
  } else {
    // Page still loading, set a handler to start the app when the page has finished loading
    document.addEventListener("readystatechange", (event) => {
      if (event.target.readyState === "complete") {
        initApp();
      }
    });
  }
}

// This is called by the wasm when everything is loaded
async function initApp() {
  // Print Knomi SDK info
  console.log(Module.GetVersionStr());
  console.log(Module.GetLegalCopyright());

  if (document.getElementById("consentButton")) {
    document.getElementById("consentButton").disabled = false;
  }
  createCamera();

  // Register controls
  registerControls();

  // Update UI
  updateUserInterface();

  // Clear feedback
  showFeedback("");

  const lastPathSegment = window.location.href.split("/").pop();
  if (lastPathSegment.includes("loggedin")) {
      console.log(document.getElementById("welcomeTitle"));
      const payloadUsername = sessionStorage.getItem("userName");
      document.getElementById(
          "welcomeTitle"
      ).textContent = `Welcome back, ${payloadUsername}`;
  }
  initRegulaUI();
}
/*

Regula Functions

*/
async function initRegula() {
  const { DocumentReaderProcessor } = window.Regula;
  const videoElement = document.getElementById("previewWindow");
  const processor = new DocumentReaderProcessor(videoElement);
  window.processor = processor;

  processor.streamParam = {
    // Camera facing mode. Can be 'environment' or 'user'. By default 'environment'.
    cameraMode: "environment",
    // Selecting a camera by ID. The camera ID can be obtained using navigator.mediaDevices.enumerateDevices();. Not set by default.
    preferredCameraId: "",
    // Video resolution. Default is 1280x720. It is set higher to better image quality
    resolution: {
      width: 1920,
      height: 1080,
    },
  };

  try {
    // Set license object ONLY on test environments. In the production build call initialize(); without a license object.
    // The licenseForDevelopment variable can be defined in config.js for test environments.
    let regulaLicense = userData.regula_license;
    if (regulaLicense !== null && regulaLicense !== "") {
      await processor.initialize({ license: regulaLicense });
    } else {
      await processor.initialize();
    }

    processor.recognizeListener = regulaListener;
  } catch (e) {
    console.log(e);
  }
}

const initRegulaUI = async () => {
  const { defineComponents, DocumentReaderService } = window.Regula;
  window.RegulaDocumentSDK = new DocumentReaderService();


  defineComponents().then(async () => {
    window.RegulaDocumentSDK.recognizerProcessParam = {
      processParam: {
        multipageProcessing: false,
        returnUncroppedImage: true,
        timeout: 20000,
        scenario: "Locate",
      },
    };
    await window.RegulaDocumentSDK.initialize({
      license: licenseForDevelopment,
    });
  });
};

const createRegulaUI = () => {
  const container = document.querySelector("#documentContainer");
  if (!container) return;
  container.innerHTML = "";
  const documentReader = createRegulaElement({
    el: "document-reader",
    target: container,
  });

  if (documentReader) {
    const faceElement = document.querySelector("#previewWindow");
    const overlayElement = document.querySelector("#ovalOverlay");
    if (faceElement) {
      faceElement.style.visibility = "hidden";
    }
    if (overlayElement) {
      overlayElement.style.visibility = "hidden";
      overlayElement.style.display = "none";
    }
  }
};

const createRegulaElement = ({ el, target, text, id }) => {
  const element = document.createElement(el);
  if (text) element.textContent = text;
  if (id) element.id = id;
  element.style.setProperty("--font-size", "10px");
  target.append(element);
  return element;
};

const validateDocumentRegula = async (strImageFront, strImageBack = null) => {
  if (strImageFront == null) {
    throw "null image passed to validateDocumentRegula";
  }
  if (strImageBack == null) {
  }
  let payload = {
    enrollmentToken: enrollmentToken,
    documentsInfo: {
      documentImage: [
        { image: strImageFront, lightingScheme: 6, format: ".jpeg" },
      ],
    },
    jwt: `${jwt}`,
  };
  if (strImageBack != null) {
    payload.documentsInfo.documentImage.push({
      image: strImageBack,
      lightingScheme: 6,
      format: ".jpeg",
    });
  }

  let videoSpinner = document.querySelector("#loadingSpinnerVideo");
  videoSpinner.classList.remove("d-none");
  showFeedback("Validating document...");
  try {
    const validationResult = await postPayloadFetch(docValidationUrl, payload);
    videoSpinner.classList.add("d-none");
    if (
      validationResult.matchResult &&
      validationResult.documentVerificationResult
    ) {
      showResults("Document verified!!");
      updateUserInterface();
    } else if (
      validationResult.matchResult &&
      !validationResult.documentVerificationResult
    ) {
      showResults("Document could not be verified.");
      updateUserInterface();
    } else if (
      !validationResult.matchResult &&
      validationResult.documentVerificationResult
    ) {
      showResults("Face match failed.");
      updateUserInterface();
    } else if (!validationResult.matchResult &&
      !validationResult.documentVerificationResult) {
      showResults("Face match failed.");
      updateUserInterface();
    } else {
      showResults("Poor image quality. Please try again.");
      updateUserInterface();
    }
  } catch (error) {
    showResults("Server Error Validating Document");
    updateUserInterface();
  }
};

const resizeObserver = new ResizeObserver((entries) => {
  let mainCard = document.getElementById("mainCard");
  if (mainCard?.clientHeight != entries[0].contentRect.height) {
    // mainCard?.setAttribute(
    //   "style",
    //   "max-height: " + entries[0].contentRect.height + "px"
    // );
  }
});

function findObject(container, propertyName) {
  let obj = null;
  for (const section of container) {
    if (section.hasOwnProperty(propertyName)) {
      obj = section;
      break;
    }
  }
  return obj;
}

const changeCaptureTargetNew = async () => {
  const video = document.querySelector("#previewWindow");
  const stream = video?.srcObject;
  const videoTrack = stream?.getTracks()?.find(track => track.kind === "video");
  const videoID = videoTrack?.getSettings().deviceId;
  pauseAndStopCamera();
  showFeedback("Capturing Document");
  clearRegionOfInterest();
  await stopTracks();
  await createRegulaUI();
  const faceImageButton = document.getElementById("faceImage");
  faceImageButton.classList.add("d-none");
  const documentReaderComponent = document.querySelector("document-reader");
  const documentReaderContainer = document.querySelector("#documentContainer");
  if (documentReaderContainer) {
    documentReaderContainer.classList.remove("d-none");
  }
  documentReaderComponent.settings = {
    closeButton: false,
    changeCameraButton: false,
    copyright: false,
    captureButton: false,
    skipButton: true,
    ...(cameraObj.IsMobileDevice() ? {} : { cameraId: videoID }),
    scenario: "Locate",
    devLicense: licenseForDevelopment,
  };
  console.log(documentReaderComponent);
  let mainCard = document.getElementById("mainCard");
  resizeObserver.observe(documentReaderContainer);
  documentReaderContainer.addEventListener("document-reader", (event) => {
    if (event.detail.action == "ELEMENT_VISIBLE") {
      setTimeout(() => {
        // mainCard?.setAttribute(
        //   "style",
        //   "max-height: " + documentReaderContainer.offsetHeight + "px"
        // );
        if (mainCard?.clientHeight)
          centerMainCardInView(mainCard);
      }, 100);
    }
  });
  if (!documentReaderComponent) return;
  documentReaderComponent.addEventListener("document-reader", (event) => {
    if (
      event.detail.action === "FILE_PROCESS_STARTED" ||
      event.detail.action == "PROCESS_FINISHED"
    ) {
      resizeObserver.unobserve(documentReaderContainer);
      requestAnimationFrame(() => {
        // mainCard?.setAttribute("style", "max-height: 40vh");
      });
      if (!documentReaderContainer) return;
      documentReaderContainer.classList.add("d-none");
    }

    if (event.detail.action === "NEW_PAGE_STARTED") {
      window.RegulaDocumentSDK;
    }

    if (event.detail.action === "PROCESS_FINISHED") {
      let imageFront = null;
      let imageFrontCropped = null;
      let imageBack = null;
      let imageBackCropped = null;
      isCameraInitialized = false;
      isAutocapturing = false;

      // Extract the images in the Regula response
      
      const data = event.detail.data;
      const fieldList = event.detail.data?.response?.images?.fieldList;
      if (
        fieldList &&
        (event.detail.data?.status === 1 || event.detail.data?.status === 2)
      ) {
        for (const section of fieldList) {
          if (
            section.hasOwnProperty("fieldType") &&
            section.fieldType === 207
          ) {
            for (const section2 of section.valueList) {
              if (
                section2.hasOwnProperty("containerType") &&
                section2.containerType === 1
              ) {
                if (
                  section2.hasOwnProperty("pageIndex") &&
                  section2.pageIndex === 0
                ) {
                  imageFrontCropped = section2.value;
                }
                if (
                  section2.hasOwnProperty("pageIndex") &&
                  section2.pageIndex === 1
                ) {
                  imageBackCropped = section2.value;
                }
              }
              if (
                section2.hasOwnProperty("containerType") &&
                section2.containerType === 16
              ) {
                if (
                  section2.hasOwnProperty("pageIndex") &&
                  section2.pageIndex === 0
                ) {
                  imageFront = section2.value;
                }
                if (
                  section2.hasOwnProperty("pageIndex") &&
                  section2.pageIndex === 1
                ) {
                  imageBack = section2.value;
                }
              }
            }
          }
        }
      }
      validateDocument(imageFront, imageBack);
    }
  });
};

/*

WP Helper Functions

*/
const checkButtonStatus = () => {
  fetch(userData.ajax_url, {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
    },
    body: "action=check_button_status",
  })
    .then((response) => {
      if (response.ok) {
        return response.json();
      } else {
        throw new Error("Network response was not ok.");
      }
    })
    .then((data) => {
      if (data.disable_old_button) {
        if (window.location.pathname.includes("/verification-required/")) {
          window.location.href = userData.cart_url;
          return;
        }

        if (isBlockBasedCheckout()) {
          // Handle block-based setup
          const consentButton = document.getElementById("consentButton");
          const wcButton = document.querySelector('a.wc-block-components-button.wp-element-button.wc-block-cart__submit-button.contained');
          const placeOrderBtn = document.querySelector('.wc-block-components-checkout-place-order-button');

          if (consentButton) {
            consentButton.classList.add('d-none');
          }
          if (wcButton) {
            wcButton.classList.remove('d-none');
          }
          if (placeOrderBtn) {
            placeOrderBtn.textContent = "Place Order";
            placeOrderBtn.disabled = false;
          }
        } else {
          // Handle non-block-based setup
          const consentButton = document.getElementById("consentButton");
          const placeOrderBtn = document.getElementById("place_order");

          if (consentButton) {
            const newLink = document.createElement("a");
            newLink.href = userData.checkout_url;
            newLink.className = "checkout-button button alt wc-forward wp-element-button";
            newLink.textContent = "Proceed to Checkout";
            consentButton.parentNode.replaceChild(newLink, consentButton);
          }
          if (placeOrderBtn) {
            placeOrderBtn.textContent = "Place Order";
          }
        }
      }
    })
    .catch((error) => {
      console.error("Error:", error);
    });
};

function isBlockBasedCheckout() {
  return typeof wp !== 'undefined' && wp.data && wp.data.select('wc/store/checkout');
}