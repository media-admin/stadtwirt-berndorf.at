=== FluentCRM Pro ===
Contributors: wpmanageninja
Tags: fluentcrm
Requires at least: 4.5
Tested up to: 6.8
Requires PHP: 7.1
Stable tag: 2.9.84

FluentCRM Pro Version

== Installation == 
if you already have FluentCRM plugin then You have to install this plugin to get the additional features.

Installation Steps:
-----
1. Goto  Plugins » Add New
2. Then click on "Upload Plugins"
3. Then Click "Choose File" and then select the fluentcampaign-pro.zip file
4. Then Click, Install Now after that activate that.
5. You may need to activate the License, and You will get the license at your WPManageninja.com account.

Manual Install:
------------------------
Upload the plugin files to the /wp-content/plugins/ directory, then activate the plugin.

== Change Log ==

= 2.9.84 (Date: October 27, 2025) =
New: FluentCart filters added to 'Check Condition' in Automations
New: Paid Memberships Pro purchase history in contact profile
Fixed: Contact status update issue when re-syncing existing WooCommerce customers
Fixed: 'Resend Unopened Emails' not working in campaigns
Fixed: Active subscription dynamic segment for WooCommerce
Fixed: LifterLMS import failures
Fixed: Duplicate email open tracking
Fixed: Sorting by open rate and click rate in the campaigns table
Fixed: Paymattic subscription amount shown in Purchase History

= 2.9.80 (Date: October 14, 2025) =
New: Fluent Cart Order & Subscription Triggers
New: Fluent Cart Purchase History in Contact Profile
New: Fluent Cart Import Customers
New: Export/Import List
New: Tag or List wise contact growth report
Improvement: 'Prefix' Column Added To Subscribers Table
Improvement: Enhanced ‘Add Existing Company’ In Contact Company Search With Initial Suggestions
Improvement: Contact’s Current Status Column Added In Individual Report Section Inside Funnel Report
Improvement: Group name of contact's custom fields is now editable
Improvement: Include recipient name in mailer send data
Improvement: Open Rate And Click Rate Columns In Email Campaigns Table Are Now Sortable
Fixed: Currency Issue Fix In Funnel Report Chart
Fixed: Individual Email Display Issue For Custom Email Address In Funnel Report
Fixed: Monthly Subscriber Growth Chart Issue Solve To Disambiguate Months By Year
Fixed: Custom Reply To Name, Reply To Email Issue Solved In Email Header For Custom Email Within A Contact’s Profile Section
Fixed: 'includes all of' condition issue in automation condition
Fixed: Ab Cart price format issue in email

= 2.9.65 (Date: August 07, 2025) =
New: Tag export and import functionality
New: Custom fields in email preference forms
New: One-click reset of filters for contacts
Improvement: Enhanced contact filtering with First Name and Last Name options
Improvement: Comprehensive contact import from CSV now includes tags and lists
Improvement: Contact status added as an automation condition
Improvement: Bulk actions now support selecting all companies
Improvement: Pagination support added for improved list navigation
Improvement: Email template preview functionality
Improvement: Background processing for large contact database exports
Improvement: Enhanced Voxel theme integration detection
Improvement: Action hooks for dynamic coupon metadata management
Improvement: WordPress user meta multiple checkbox values handled in SmartCodes
Fixed: Resend functionality issues in failed email delivery
Fixed: Form template null error during creation
Fixed: Multiple custom tab display issues in company profiles
Fixed: Lifetime purchase value incorrect issue (for woo partial payment addon)
Fixed: Vertical stretching issue with product images in the woo order table
Fixed: Bulk deletion issue in automation funnels
Fixed: Email campaign import issues for visual builder
Other improvements and bug fixes

= 2.9.60 (Date: May 14, 2025) =
New: Introduced built-in templates feature
New: List-wise double opt-in email settings now available
New: Voxel New Order Placed Trigger
New: Option to send custom emails as transactional email
New: Custom menu tab functionality added on the company profile page
New: Dynamic segments based on active WooCommerce subscriptions
New: Added Contact Unsubscribe hook for enhanced customization
New: Bulk add/update contacts REST API endpoint
Fixed: Sorting Issue in Purchase History tab in Contact Profile
Fixed: Custom Field multi-line text Issue
Fixed: CSV export issue with the contacts filter
Fixed: Links tracking issue in Link Stats in Campaign details.
Other Improvements & Bug Fixes

= 2.9.50 (Date: April 17, 2025) =
New: Shortcode support for multiple email campaign archives
New: Shortcodes are now searchable
New: Voxel Integration ( Product purchase history in contact )
New: IPv6 compatibility Added
Improvement: WordPress version 6.8 compatible
Improvement: Toggle for column visibility in automation funnels table
Improvement: Unsaved changes warning in block editor
Improvement: More translations string added
Improvement: Search functionality for System Logs
Fixed: Global footer displaying incorrectly in email previews
Fixed: Custom field values couldn't be cleared once set
Fixed: Custom email footer settings import issue
Fixed: Encoding issue in Custom Field Text
Fixed: Label search functionality errors
Fixed: Fatal error during funnel import process
Fixed: AB Cart Tag and list not removing after order completion

= 2.9.48 (Date: March 20, 2025) =
New: Re-apply Option for Completed Sequence
New: Tags and Lists are now searchable in Dynamic Selection
New: Selectable Custom Fields now Editable & Sortable
New: Smartcodes for WooCommerce Subscription Triggers
Improvement: Added Copy email and phone from contact lists
Improvement: Tags and lists display in ascending order
Improvement: LearnPress course finished hook Updated
Improvement: LatestPostBlock now displays all custom post types
improvement: Added currency to Shipping and Tax Total
Improvement: Introduced Filter to manage new bounced email
Improvement: Tags and subscriber lists now sorted in ascending order
Improvement: Added operator type selection for taxonomy filters in LatestPost Block
Fixed: Padding, Margin, and Line-Height issues inside Column block
Fixed: Dynamic coupon amount issue with existing template
Fixed: Excerpt length of LatestPostBlock issue
Fixed: UpdateContactProperty Action float subtraction issue
Other Improvements & Bug Fixes

= 2.9.45 (Date: February 24, 2025) =
New: Subscription Cancelled Trigger (Fluent Forms)
New: Subscription Payment Received Trigger (Fluent Forms)
New: FluentForm Subscriptions Widget in Contact Profile
New: Update Custom Fields Using Bulk Actions
New: Filter option for failed emails
New: Show non-recurring memberships in MemberPress Widget
New: Woo Subscription Cancelled trigger
New: Option to sort custom fields
New: Wishlist Membership Widget in Contact Profile
Improvement: The slug retains one character even after the title is cleared
Improvement: Display which user sent the campaign
Improvement: All tables with adjustable column widths
Improvement: Redesigned the Addons section with improved UI/UX
Improvement: Added tooltip for Skipped AB cart status
Improvement: Added a button to copy the bounce handler URL
Fixed: Custom numeric field filter issue
Fixed: WooCommerce Coupon Discount amount not working
Other Improvements & Bug Fixes

= 2.9.40 (Date: January 22, 2025) =
New: Quick Search in Automation Actions, Benchmarks, Goals
New: MemberPress Subscriptions Widget
New: Export/Import Email Campaign
New: Export/Import Email Campaign Contacts
New: Brevo (ex Sendinblue) Bounce Handler
New: Support for Polish Characters in slug (Tags/Lists)
Improvement: Contact filtering options: Never Clicked/Opened
Improvement: Quick preview added in email templates
Improvement: Post Image Type for Latest Post block
Improvement: Current date in Update Contact Property action in Automation
Improvement: WooCommerce Product Image Styling (order_items_table)
Improvement: Back Button for Campaign Archives
Improvement: Restart section added in ‘Remove From List’ Trigger
Improvement: Added 'Check All' option contact exporter
Improvement: More Filters in email campaign archive
Improvement: Added ‘Select All’ tag/list option while importing contacts
Improvement: UI Improvements (Automation Label Color)
Improvement: Smoother One-click Unsubscribe
Improvement: Coupon systems support for multi-vendor/extensions along with woocommerce
Improvement: Added Gravatar & Fallback Compliance for Contact Avatar
Fixed: Spammed/Complained Status Issue in Bounce Handler
Fixed: Theme colors not displaying in Emails
Fixed: Pagination for recurring campaign emails
Fixed: Sync WooCommerce order (trashed order issue)

= 2.9.31 (Date: December 27, 2024) =
New: Added Email Preview in Campaigns
Improvement: AB Cart item table responsive
Improvement: User delete option sync between compliance settings and general settings
Improvement: Replaced google fonts with Bunny
Fixed: AB Cart Recovered Revenue issue
Fixed: Campaigns revenue report issue
Fixed: Latest Post Block random sort issue
Fixed: Table alignment issue in blocks
Fixed: Number values in the 'Text' custom field misinterpreted as date issue.
Fixed: Variable button size not working in block editor
Fixed: MailChimp Migration import limitations
Fixed: Theme & default color issue in editor
Other Improvements & Bug Fixes

= 2.9.30 (Date: December 09, 2024) =
New: Labels in Automations & Campaign
New: WordPress date format support in Custom Field
New: Test Outgoing Webhook functionality
Improvement: UI enhancements for Lists & Tags popover
Improvement: Corrected date handling
Improvement: Auto-Mapping CSV Fields with Custom Fields
Improvement: Better SQL Queries
Improvement: Users now searchable in manager settings
Fixed: Default link color issue while editing the email template
Fixed: Default values not working for Smartcode (manage_subscription_html, unsubscribe_html)
Fixed: Ordering in Dashboard Chart
Fixed: Campaign Revenue Report issues
Fixed: URL decode issue in A/B Testing for Campaigns
Fixed: Some Deprecation Warnings
Other Improvements & Bug Fixes

= 2.9.25 (Date: October 16, 2024) =
- New: Export/Import Recurring Campaign
- New: Smart Code support in Custom Email Address field
- New: Customer Profile button for EDD
- New: Added internal description to the funnels page
- Improvement: Product Image  & Currency Added in Ab Cart Details/Email
- Improvement: Multiline Custom Field
- Improvement: UX in Latest Post Block
- Improvement: Added tax row in Abandoned Cart
- Improvement: Abandoned Cart Details Mobile Responsiveness
- Improvement: Sorting Option in Purchase History in Contact for Woo/EDD
- Improvement: Changing product now possible from block sidebar
- Fixed: URL encoding Issue
- Fixed: Automation Wait Delay Issue
- Fixed: Dashboard Chat Dates Order
- Fixed: Email Editor LetterCase
- Fixed: Company Custom Field CSV Import Issue
- Fixed: Company Custom Field Issue while creating
- Fixed: Smart Code wp.url Issue
- Fixed: Ab Cart Smart Code Issue
- Fixed: MemberPress Contact Import Issue
- Other Improvements & Bug Fixes

= 2.9.24 (Date: Aug 20, 2024) =
- Hotfix: Abandoned Cart Condition Issue Fixed

= 2.9.23 (Date: August 19, 2024) =
- Added Custom Field or Date of Birth on Wait Time in Automation
- Added WooCommerce Variation Product on Advanced Filter and Automation Conditions
- Added keyboard ⌘ (or ctr) + s to save emails and automations
- Added Links Report for Individual Contacts
- Fixed: Dynamic Segment Contact Count Issue
- Fied: WooCommerce Revenue Report Issue fixed on Contact Profile
- Added contact.company.* smartcodes
- Added SMTP2GO Bounce Handler

= 2.9.20 (Date: August 12, 2024) =
- New: Abandoned Cart
- New: Built-in Automation Templates
- New: FluentSMTP logs to the Emails Section of Profile
- New: Email Filter to the Emails Section of Profile
- Fixed: Email Editor Issue
- Fixed: ActiveCampaign Import Contacts Issue
- Fixed: Event Tracking Fetch Issue
- Fixed: Sending Double opt-in Email
- Fixed: Webhook Issue
- Fixed: Automation Twice Run Issue
- Improvement: UI of the Custom Fields
- Other Improvements & Bug Fixes

= 2.9.1 (Date: June 04, 2024) =
- Fixed Dynamic Posts Block Query Issue

= 2.9.0 (Date: May 29, 2024) =
- New: WooCommerce Coupon Creation Action
- New: Custom Fields on Company Module
- New: Bricks Builder Conditional Elements Integration
- New: All Automations Activity Feed
- New: Create Tags on the fly from the contact profile
- New: Added taxonomy filter for Latest Post Block
- CSV Import Improvement
- Contact Bulk Actions Update - Now you can select all the contacts from the filter
- Improvement: Improved FluentCRM PHP API for Contacts
- Improvement: Improved FluentCRM REST API for Contacts
- Improvement: Advanced Filter Improvements
- Improvement: Automation Improvements & Bug Fixes
- Fixed: Thrive Suites Integration Issue Fixed
- Fixed: PHP 8.x Compatibility Issue Fixed for CSV import

= 2.8.45 (Date: March 01, 2024) =
- New: WooCommerce Subscription Expiration Trigger
- New: WP User Role Based Segmentation for Advanced Filters
- New: BuddyBoss / BuddyPress Tags for Invites and Group Membership
- Performance: Improved Email Sending Database Queries
- Fix: WooCommerce Address Field Syncing Issue Fixed
- Fix: LearnDash Course SmartCode Issue Fixed
- Elementor Form Integration Improvement
- Security Improvement: Company Logo Auto Fetching File-Type Check Added

= 2.8.44 (Date: Feb 06, 2024) =
- Improved Action Scheduler for Email Sending
- Added Campaign Email Shareable Link
- New Smart Codes - WP User
- Improved Contact Profile API
- Bug Fixes and Improvements
- Improved Data Clean-Up Tool
- Improved Security on Auto Login for Smart Links

= 2.8.43 (Date: Jan 30, 2024) =
- Auto Login Option with Smart Links
- Add All Post Type for Recurring Campaigns Conditions
- Improved WooCommerce Orders History and Sync
- Email Campaign Analytics Improvement
- Fixed Template Import issue


= 2.8.42 (Date: Jan 28, 2024) =
* Support For WooCommerce HPOS Integration
* Fixed Customer's Order History Issues
* Improve Litespeed Cache Compatibility

= 2.8.40 (Date: Jan 26, 2024) =
* Multi Threader Email - Send Emails faster
* Custom Contacts Fields Grouping
* Event Tracking for contacts
* Latest Post Block improvement
* One-click List-Unsubscription Header
* System Logs for debugging
* New Goal/Benchmark Added for SureCart (pro)
* New Automation Trigger: Paid Membership Pro - Membership Cancelled (pro)
* Scheduled Jobs improvement
* Added Postal Server support for Email Bounce Handling
* Webview for Email Campaigns improvements and privacy improvements
* Other Improvements & Bug Fixes

= 2.8.33 (Date: Nov 03, 2023) =
* Restart Feature in Tag Removed Automation Action
* Fixed Custom Footer Issue in Recurring Campaign
* Other Improvements and Bug Fixes

= 2.8.32 (Date: Oct 27, 2023) =
* WooCommerce HPOS Compatibility Added
* Conditional Checks for LearnDash groups and items issue fixed
* Fixed Restart issue in UserLogin Trigger
* UI Improvement of Latest Post Block in Email Builder

= 2.8.30 (Date: Sep 05, 2023) =
* Improvement on Company module
* New trigger: Company added to contact Trigger
* New Trigger: Company Removed Trigger
* Company Specific Automation Actions
* Custom email preference management page
* New Trigger: Contact Created
* Navigation and UI improvements
* Duplicate segment or export contacts
* Other improvements including translatable strings, permissions in CRM managers
* Other Bug Fixes

= 2.8.20 (Date: Jul 18, 2023) =
* Campaign Email Scheduling and sending speed optimized
* DeepIntegration with TutorLMS
* Integration with SureCart
* New Trigger: SureCart Order payment refund
* New Trigger: SureCart Payment success
* New Triggers: Tutor LMS Lesson Completed
* New Triggers: RCP Subscription Expired
* Advanced Filtering: Empty/Not Empty Filter
* Confirmation prompt for email campaigns
* Double Opt-in Email pre-header
* Ability to delete contact profile picture
* Company attach/detach from bulk actions
* Autofill for Woo commerce checkout fields
* Schedule Email by time range

= 2.8.0 (Date: Apr 14, 2023) =
* Added Company module
* More detailed contact overview
* Massive UI enhancements
* FluentCRM Navigation Experience
* Ability to check email preview for specific contacts
* New WooCommerce Subscription Triggers (Pro)
* Improvements and bug fixes

= 2.7.40 (Date: Mar 01, 2023) =
* Fixed recurring emails date time issues
* Email Conditional Sections issues fixed
* List & Tag selection UI improved
* Replaced Google Fonts with BunnyCDN fonts for frontend rending
* Campaign Email Activity Improvements
* Fixed Redirecting issues for non-unicode characters
* Fixed import issue for Restrict Content Pro

= 2.7.3 Date: Feb 10, 2023 =
* Fixed Versioning Issues

= 2.7.1 Date: Feb 06, 2023 =
* Fixed Recurring Campaign Timezone issues
* Added New Smartcode for latest post title and Custom Date Format for smartcode
* Send Email Notification for new draft Email Campaign for recurring Campaign
* Elastic Email bounce handler added

= 2.7.0 Date: Jan 23, 2023 =
* Latest post block
* Recurring campaign
* Improvements in Contact Filtering
* New developer documentation
* Refactored plugin and performance improvements
* Enhancements and bug fixes

= 2.6.52 Date: Nov 24, 2022 =
* Conditional Sections on Visual Builder
* Email Preview issue on Campaign Review Screens
* Added Tag Based Redirect after Double Optin (Check Settings -> Double Optin)
* Date Time Filters issue fixed for custom Fields
* Template Import / Export
* Save as Template from Campaign Screen

= 2.6.5 Date: Nov 17, 2022 =
* Brand New Drag and Drop Email Builder
* Email Audit for invalid Links for Email Editor
* Integration Improvements (Woo, BuddyPress)
* In-Page Documentation for top level feature pages
* UI & UX improvement across the full application
* Better Mobile optimized screens
* User registration automation trigger issues are solved

= 2.6.0 (Date: Oct 20, 2022) =
* New Trigger: Birthday Automation
* New Trigger: Leave from a Course (LearnDash)
* New Action: Remove WordPress User Role
* Advanced Contact Filters: Email campaign, sequence, automation activity conditions
* New WooCommerce/EDD/LearnDash/LifterLMS conditions
* Experimental: Email Archives in the frontend
* Faster email editor
* Select and modify email template blocks in bulk
* Improved Email Sequences
* Improvement on Automation Goals
* Experimental features for Faster Contact Navigations, Date Formats
* UI & UX Improvements
* Bug fixes and minor improvements

= 2.5.95 (Date: Aug 19, 2022) =
* Advanced wait action in Automation
* Added restart automation to all(almost) triggers
* Sequence filtering for automation
* View revenue for specific emails
* Create Fluent Support tickets from Automation
* Split test automation scenarios
* Revenue metrics in email sequences
* More conditions in Advanced Filtering (pro)
* Enable/Disable auto sync for integrated tools
* Email preference management short-code
* Detailed CRM reporting (pro)
* Pre-populate Fluent Forms data from FluentCRM
* Bug fixes & improvements

= 2.5.93 (Date: July 07, 2022 ) =
* Improved scheduled campaigns
* Huge Performance Improvement
* Fixed Country Name Filters
* Improved Contact Imports
* WP User Sync Issue fixed
* Contact Exclude from campaign fixed
* WP Ultimo conflict issue resolved

= 2.5.91 (Date: May 28, 2022 ) =
* Fixed WooCommerce Data Sync

= 2.5.9 (Date: May 27, 2022 ) =
* WooCommerce Subscriptions integration
* EDD Software Licensing integration
* MemberPress Contact Importer
* Export contacts by advanced filters
* Export/Import Email Sequences
* New bulk action: send double opt in
* Manual actions: delete contact, add contact to automation & email sequences
* Fetch profile picture from Fluent Forms entry
* Smartcode/Merge tags Transformer
* Improvements and bug fixes

= 2.5.7 (Date: March 07, 2022 ) =
* Added Merge tags for WooCommerce, Affiliate WP, LearnDash and LifterLMS
* Fixed issue with LearnDash events
* Added option to add contacts to an Automation
* Added option to add contacts to an Email Sequence
* Fixed campaign sending issue for some server
* Double Optin issue has been fixed
* Integration Improvements
* UI Improvements

= 2.5.6 (Date: February 28, 2022 ) =
* Added Auto Migration from ActiveCampaign, MailerLite, MailChimp, Drip, ConvertKit
* Fixed CSV Import Issue for duplicate emails
* Email Builder Issues fixed for the latest version of WP
* Improved Contact Filtering
* Integration Improvements
* Improved UI

= 2.5.5 (Date: February 07, 2022 ) =
* Bulk Actions Improvements for Contacts
* Add Name Prefix filter to Advanced Filter
* WooCommerce Data Sync and Automations issues Fixed
* EDD Advanced Filter and Automation Triggers Fixed
* Email sequence issues fixed
* Webhook issues fixed
* UI & UX improvements

= 2.5.4 (Date: February 06, 2022 ) =
* (HOT FIX) Fix Multiple Automation Trigger for Woo and EDD

= 2.5.3 (Date: February 01, 2022 ) =
* Compatability with WordPress 5.9
* Improved Email Builder
* CSV import duplicate data issue fixed
* Automation Improvement
* Tagging Improvement
* Fixed Advanced Filters for Woo,EDD,LearnDash and LifterLMS
* Fixed JSON issue for HTTP Action
* UI&UX improvement in several screens across the app

= 2.5.1 (Date: January 28, 2022) =
* Micro-target contacts!
* Send targeted email campaigns
* Create better dynamic segments
* Run automation with advanced conditional logic!
* Synchronize WooCommerce, EDD, LifterLMS, and LearnDash data!
* Import EDD & WooCommerce contacts by product purchases!
* View customer summary at a glance!
* Advanced reporting (for WooCommerce, EDD, LifterLMS, and LearnDash)
* Enroll/Remove students and Add/Remove memberships, automatically!
* Automate emails for trial products
* New Goal: Email Sequence Completed
* Beaver Builder subscription form integration
* Bug fixes & improvements

= 2.3.1 (Date: July 20, 2021) =
* BuddyBoss/BuddyPress Integration
* ThriveCart Integration (Addon)
* LearnPress Integration
* Dynamically Import Existing users from LMS/Membership/BuddyBoss
* Email Editor Improvement
* Persistent Contact Page
* Dynamic Segment Improvement and Bulk Operations
* Automation Funnel Improvement
* Integration improvements
* ... and so many new features and improvements


= 2.2.0 (Date: July 20, 2021) =
* Whole New Dashboard Design
* Added Lots of WooCommerce Integrations
* More integration added with LifterLMS, LearnDash and TutorLMS
* Outgoing Webhook in Automation
* WooCommerce Conditional Block and new action and triggers added
* User Registration Action Block added to Automation
* Custom fields improvements
* Added Plain Text Email Template
* Added Fluent Forms force subscribe feature
* User role based tagging feature added
* Added Redirection Option after Double-Optin
* Email Builder Blocks Improvements
* Add Option to remove contact on WP User delete
* Showing in details purchase history from WooCommerce on Contact Screen
* Webhook bounce handler with all major Email Service providers

= 2.0.4 (Date: June 03, 2021) =
* This is a minor update (no new feature, sorry!)
* Webhook issue fixed for some providers
* ENd Funnel Issues Fixed
* Fix CRON Issues for some specific server
* UI Color issue fixed
* Fluent Forms conditional issues fixed

= 2.0.3 (Date: May 07, 2021) =
* Add Selected Days to Sending Emails for Email Sequences
* Fix CRON Jobs issues
* Image alignment issue for Emails fixed

= 2.0.2 (Date: May 03, 2021) =
* Condition Content Block for Oxygen Builder
* Restart Automation
* Color Codes for Automation Blocks
* NEW: WooCommerce Product Refund Trigger
* New: Notes and Activities Action Block
* Email Sending Speed Improvement
* Build-in Documentation Page
* Lots Improvements and Fixes

= 2.0.1 (Date: March 31, 2021) =
* CSV Issue Fixed
* New: Added Contact Property Update from Automation
* New: WooCommerce Subscription Box in Checkout Page
* Automation UI improvement

= 2.0.0 (Date: March 30, 2021) =
* Multi-Path Conditional Automation Funnel
* New Automation Triggers and Blocks
* CRM Access Roles
* SmartLinks Improvements
* More Analytics Data
* Share an Automation
* Better RTL Support and Fully Translatable
* Automation Funnels Improvement
* RTL issues resolved
* Email Builder Improvements
* CSV Import issues fixed
* Email sending speed improvement
* Contact Data syncing with WordPress Users
* Overall UI and REST API improvements

= 1.1.92 (Date: January 25, 2021) =
* Added SmartLinks for adding tags and lists dynamically on click tracking
* Export Contacts Feature added
* Added Post/Page Block for Dynamic content based on tag or login state
* Added feature to delete old logs
* Added all emails activity page
* Fix issue on dynamic smart tags on email subject
* UI improvement
* Added FluentSMTP Support
