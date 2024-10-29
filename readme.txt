=== AwareID - Biometric Identity Authentication  ===
Contributors: jhicksaware
Tags: enrollment, authentication, biometrics, awareid, document verification, geolocation
Requires at least: 5.0
Tested up to: 6.6
Stable tag: 2.2.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Secure app logins without passwords, facilitate trusted transactions & reduce chargebacks by enabling easy biometric enrollment & authentication.

== Description ==

**Secure More Revenue with Seamless Biometric Enrollment & Authentication**

For robust, multi-layered security for all users, integrate AwareID's Identity Verification plugin for biometric enrollment & authentication. Verify user identities using AI-powered facial biometrics and document authentication, ensuring compliance and fraud prevention from login to checkout. Protect your app and profits and give users an experience they’ll love! 

Note: Please reach out to [Aware](https://www.aware.com/awareid-wordpress-contact/) or [email us](sales@aware.com) if you have not yet registered with our organization for biometric authentication. This is crucial as the plugin will not be able to operate without an AwareID login. 

== Strengthen Security and Compliance ==

Designed for WooCommerce implementations that demand high-security identity verification, this plugin ensures only enrolled, verified individuals can complete purchases.

AwareID (https://www.aware.com/biometric-identity-management-as-a-service/) integrates seamlessly with WooCommerce, offering:  

 - KYC Compliance
 - ID Document Verification 
 - Multi-Modal Biometric Authentication
 - Biometric Face Matching & Face Liveness
 - Geofencing, Device Risk, and Fraud Prevention 

Create customizable workflows, intuitive point-and-click configuration, and advanced biometric security settings. This plugin empowers you to tailor verification processes to align with your specific business needs, ensuring safe and compliant transactions.

== Features ==
- **Secure Customer Enrollment and Authentication**: Integrates with the WooCommerce cart or checkout flow to add an enhanced layer of security. 
- **Seamless Checkout**: Enable identity verification via face and document for both guests and registered users. 
- **Age Restriction & ID Document Verification**: Set age restrictions to prevent access to the site with sophisticated document reading capabilities.  
- **Location-based Security**: Set states from which the customer will be denied access to checkout and logging in depending on geographic location. 

== User Workflow ==
- **Guest Checkout**: Guests are prompted to verify their identity via face and document recognition during their first checkout. This verification may not be required on subsequent visits, depending on the settings.  
- **Registered User Checkout**: Users logged in but not verified are required to verify their identity before proceeding with checkout. Restrictions based on age and geographic location are enforced. 

== Why Aware? ==

**30 Years of Innovation**: Established over three decades ago, Aware holds over 80 patents in biometric technologies and a proven track record of excellence. 

**Trusted by Industry Leaders**: Our technology safeguards critical systems for clients such as NASA, government agencies, and over 150 law enforcement agencies worldwide. 

**In-House Expertise, Developed in the USA**: All solutions are developed by Aware’s expert team in the United States, ensuring the highest standards of quality, security, and innovation. 

**Inclusive Biometric Solutions**: Aware trains all  biometric systems with diverse data sets to eliminate racial bias. We also empower users to have control over identities through clear, easy opt-in and opt-out features, helping them feel secure and improving their lives. 

Whether you require white-labeled apps, native SDKs, or API integrations, AwareID offers flexible deployment options, including low-code and OpenID Connect integration. This adaptability guarantees robust protection without compromising on ease of use or customer satisfaction. 

== How to Get Started: ==

Before installing the plugin, ensure you meet the following requirements:
- An active WooCommerce installation on your WordPress site.
– An active AwareID account with appropriate credentials.  

Please reach out to [Aware](https://www.aware.com/contact/) or [email us](sales@aware.com) if you have not registered with our organization for biometric authentication. This is crucial as the plugin will not be able to operate without an AwareID login. 

== Configuring AwareID into WooCommerce   ==

To set up the plugin, configure it with your AwareID account details to enable authentication requests. 
  
**Note**: This section will detail configuration of AwareID into WooCommerce only and will serve to make sure your WordPress site can connect to AwareID to authenticate consumers when configured. When you become an Aware customer, your Customer Onboarding includes configuration of the AwareID product to meet your use case and business objectives. This is a critical step to receiving successful authentication results and should be done prior to configuring AwareID on your WordPress site.  
  
Once you are logged into your WordPress Admin Account, navigate to the Aware Verification Settings Panel. On this menu page, please input the following details: 

1. **AwareID Domain**: Input the base URL for your AwareID service.
   Example: https://awareid-yourdomain.aware-apis.com
2. **Realm Name**: Specify the account name associated with your AwareID environment. 
3. **Client Secret**: Enter the client secret key provided by AwareID. Note: This key is unique to your configuration and a vital component of securing your instance and the sensitive personal information of your customers. Please keep it confidential.  
4. **API Key**: Enter the API key which is another critical credential for connecting with AwareID. Note: This key is unique to your configuration and a vital component of securing your instance and the sensitive personal information of your customers. Please keep it confidential. 
5. **GeoCode Earth API Key**: If your configuration will be utilizing Aware’s GeoFencing feature, you need to have a GeoCode earth API key. This will be supplied to you by our Customer Onboarding Team if you are using this functionality. 
6. **IP Info API Key**: If your configuration will be utilizing Aware’s GeoFencing feature, and you wish to utilize the geolocation functionality, you need to have a IpInfo API Key. This will be supplied to you by our Customer Onboarding Team if you are utilizing this functionality. 
7. **Update Public Key**: Press update public key button to update public key for encrypted face capture.

== Admin Functionality ==

Through the AwareID Settings panel, you can configure:

- **GeoFencing Settings**: Define states or regions where access should be restricted.
- **Age Restrictions**: Set a minimum age requirement for checkout.
- **Document Authentication**: Verify document authenticity using OCR and security checks to prevent fraud. 
- **ReValidation Settings**: Choose intervals at which users must revalidate their identity.
- **Identity Verification**: Confirm user identities through multimodal biometrics or document checks.
- **Biometric Authentication**: Secure access with biometrics reducing the risk of breaches and improving user experience. 

== Detailed Service Integration Points ==

This section provides specific details about the integration points where our plugin communicates with external services, particularly the AwareID SaaS platform. Understanding these points will help users ensure compliance with relevant legal and data protection standards.  

== Authentication with AwareID using OpenID Connect ==

Purpose: Utilizes OpenID Connect protocol to authenticate users securely by generating JSON Web Tokens (JWTs). This method verifies user identities with high integrity.
Endpoint Usage: The plugin constructs $openid_url for token generation, essential for user sessions:

Examples: 
$openid_url = $awareIDConfig['domain'] . 'auth/realms/' . $awareIDConfig['realm'] . '/protocol/openid-connect/token'
$openid_url = $domain . 'auth/realms/' . $realm . '/protocol/openid-connect/token';

Security Note: URLs and realm identifiers are set during configuration to ensure custom, secure environments per customer.

== Third-Party Services and Libraries ==
This plugin uses a hybrid architecture, combining WordPress functionality with external services and client-side components for enhanced security and performance. It relies on the following third-party services and libraries:


**Server-Side Processing**:
AwareID (Managed by Final Customers)


This plugin utilizes AwareID, a SaaS platform developed by Aware, Inc., which facilitates biometric verification. Below, you’ll find general details about Aware, Inc. and a note on the AwareID platform’s specific usage. 

Purpose: AwareID is used to accurately and securely process biometrics including face, document, voice, and device profiling.

**General Website**: https://aware.com/
**General Terms of Service**: https://www.aware.com/terms-and-conditions/
**General Privacy Policy**: https://www.aware.com/dataprivacy/

– Note: The specific terms of service and privacy policies for AwareID services are managed and provided by each Aware Business Client. Users of this plugin must consult the specific AwareID environment managed by the service provider they are interacting with to review applicable terms and privacy policies.  

**GeoCode Earth**

**Purpose**: Used for geofencing functionality to determine user location.
**Website: https**://geocode.earth/
**Terms of Service**: https://geocode.earth/terms/
**Privacy Policy**: https://geocode.earth/privacy/


**IPInfo.io**

**Purpose**: Used as a fallback for geolocation when HTML5 geolocation is unavailable or denied.
**Website**: https://ipinfo.io/
**Terms of Service**: https://ipinfo.io/terms-of-service
**Privacy Policy**: https://ipinfo.io/privacy-policy


**Client-Side Components**:
**KnomiWeb** (Non-GPL)

**Purpose**: Used for face capture during the biometric verification process.
**Hosting**: Hosted on Aware Inc.'s CDN
**License**: Proprietary (non-GPL)
**Note**: This library is loaded from our CDN and is not included in the plugin files.


**Regula Document Reader SDK** (Non-GPL)

**Purpose**: Used for document capture and verification during the authentication process.
**License**: Proprietary (non-GPL)
**Website**: https://regulaforensics.com/products/document-reader-sdk/


This architecture allows us to leverage secure cloud processing while maintaining the responsiveness needed for biometric capture and initial verification.

== User Consent and Control ==
We are committed to transparency in our use of biometric authentication:

- **Consent**: Clear user consent is required before any biometric data is collected in most jurisdictions. The Aware Business Client is required to obtain these consents from consumers in advance of biometric data collection and ensure users are fully informed about the data collection process before they proceed.
- **Core Functionality**: Biometric verification is an integral part of this plugin's security measures. Users who do not wish to use biometric verification should not install or should uninstall this plugin.
- **Data Deletion**: Users can request complete deletion of their biometric data by contacting the site administrator.
- **Transparency**: We provide clear information about data collection, processing, and storage practices in our privacy policy. For questions or concerns regarding our privacy policy, please contact us at privacy@aware.com 

== Support ==
For technical support or further assistance, please contact support@aware.com.

== Data Handling and Privacy ==
Our plugin facilitates the secure authentication process by connecting your WordPress site with AwareID's backend services. It's important to note that this plugin does not store sensitive data itself:

- Data Processed: The plugin helps collect facial biometrics, document images, and geolocation data for verification purposes.
- Data Transmission: Collected data is securely transmitted to AwareID's servers for processing.
- Backend Processing and Storage: All data processing and storage occur on AwareID's secure servers, not within WordPress or this plugin.
- Data Policies: Retention periods, user rights, and compliance measures are managed by the individual AwareID service provider (your company or contracted service).
- Plugin's Role: This plugin acts as a facilitator, enabling the connection between your WordPress site and the AwareID backend. It does not store or manage the sensitive data itself.

Important Note: Important Note: As the site administrator and as the Aware Business Client, you are responsible for ensuring that your use of AwareID services, including data handling and retention policies, complies with relevant regulations such as GDPR and CCPA. Please review your internal policies and procedures to ensure appropriate consult with your specific AwareID service provider for details on their data handling practices and to set up appropriate privacy policies for your users.

== Changelog ==

= 2.2.0 =
* Enhancement: Full support for block based WooCommerce.
* Fix: Fixed issue with email not being sent on automatic account creation.

= 2.1.5 =
* Enhancement: Removed lottie, using base gif.
* Fix: Various bug fixes.

= 2.1.4 =
* Fix: Fixed issue with DotLottie player hosted locally.

= 2.1.3 =
* Functionality: Updated initial checkout logic to include a consent and information dialog.
* Security: Unescaped nonce fixed.
* Enhancement: Removed CDN links for Lottie images and Regula.
* Fix: Updated more function names.
* Fix: Changed Lottie .mjs to .js.

= 2.1.2 =
* Security: Fixed unescaped function - awareid_ts_custom_checkout_button()

= 2.1.1 =
* Security: Added nonce to search and pagination.
* Testing: Tested with WordPress 6.6

= 2.1.0 =
* Security: Updated IP Info API Key to no longer be hard coded.
* Security: Improved escaping throughout the plugin.
* Enhancement: Removed CDN links for Bootstrap, Font Awesome, Select2, and Lottie Player, now using local files.
* Enhancement: Hosted KnomiWeb on CDN as it is a non-GPL service.
* Fix: Corrected text domain issues for better internationalization.
* Fix: Updated generic function names to be more specific to the plugin.

- 2.0.0 Initial release.