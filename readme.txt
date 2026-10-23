=== Delete Account for Easy Digital Downloads ===
Contributors: DaanvandenBergh
Tags: easy digital downloads, gdpr, delete account, privacy, block
Requires at least: 6.3
Tested up to: 7.1
Stable tag: 1.0.0
Requires PHP: 7.4
License: MIT
License URI: https://opensource.org/licenses/MIT

Let your customers delete their own Easy Digital Downloads account (GDPR's right to erasure), with a block.

== Description ==

Customers asking you to delete their account is a manual job in Easy Digital Downloads. With this plugin, they do it themselves: place the **Delete Account** block on e.g. your Account page, below the profile editor.

The block tells your customers what deleting their account does, and asks them to confirm with a checkbox. The button stays disabled until they do.

= The block =

* Edit the heading, introduction, list, checkbox label and button text right in the block editor.
* Style it with a border, colors and spacing.
* `{site_name}` is replaced with your site's name.
* Only logged-in customers see it. After deleting their account, they see a confirmation.
* The list items about subscriptions and license keys are only shown to customers who have them.

= What happens when a customer deletes their account =

1. Their active subscriptions (EDD Recurring Payments) are cancelled at the payment gateway, so they're not charged again. If that fails, the account isn't deleted, and you're notified.
2. Their license keys (EDD Software Licensing) are detached from the account. They keep working until they expire, and can still be renewed with the license key at checkout.
3. WordPress' personal data erasers run, the same as Tools > Erase Personal Data. Easy Digital Downloads anonymizes the customer, and anonymizes or deletes their orders depending on each order's status (see the FAQ). Download logs, reviews, comments and AffiliateWP customer records are anonymized too.
4. If Brevo for Easy Digital Downloads is set up, the contact is deleted from Brevo.
5. The WordPress user is deleted and logged out.
6. You get an email with what was done, and what couldn't be erased, if anything.

= Who can't delete their account here =

Staff, affiliates (AffiliateWP) and customers with orders Easy Digital Downloads may not change (e.g. a payment that's still processing) are asked to contact you instead. Set the link to your contact page in the block's settings.

= Security =

Customers can only delete their own account, from the form shown to them while logged in. Requests from other sites are refused.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/edd-delete-account`, or install the zip from the latest GitHub release, and activate it.
2. Edit your Account page and add the **Delete Account** block, e.g. below the profile editor.
3. In the block's settings, enter the URL of your contact page.
4. Go to Downloads > Settings > Policies > Privacy > Order Statuses, and make sure each status is set to Anonymize or Delete, e.g. Renewal. Easy Digital Downloads won't anonymize a customer with an order set to Do Nothing.

== Frequently Asked Questions ==

= Are orders and invoices deleted? =

By default, only failed and abandoned orders are deleted. What happens to an order depends on its status, as set in Downloads > Settings > Policies > Privacy > Order Statuses. By default:

* Complete, refunded, revoked and pending orders are anonymized: the name, address lines and email address are removed, but what was bought, when, for how much, and the city, postcode and country are kept, because tax law requires it.
* Failed and abandoned orders, which were never paid, are deleted.
* Processing orders are left as they are, and their customer can't delete their account here. Renewals follow your setting for complete orders, unless you set Renewal yourself.

You can change this per status (Anonymize, Delete or Do Nothing) in Downloads > Settings > Policies > Privacy > Order Statuses. Invoices in other systems (e.g. your accounting software) aren't touched.

= Can a customer renew a license key after deleting their account? =

Yes, with the license key at checkout. They won't get renewal reminders anymore, because their email address is gone.

= Can I change who can delete their account? =

Yes, with the `edd_delete_account_customer_roles` filter. Customers with other roles than these (default: subscriber, edd_subscriber and customer) can't. Use `edd_delete_account_blocked_reason` to prevent it for specific customers.

== Changelog ==

= 1.0.0 =
* First release: the Delete Account block.
