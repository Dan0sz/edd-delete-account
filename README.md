## Delete Account for Easy Digital Downloads

Lets customers delete their own account (GDPR's right to erasure), with a **Delete Account** block you place on e.g.
your Account page, below the profile editor.

The block lists what deleting the account does, and asks to confirm with a checkbox. Its heading, introduction, list,
checkbox label and button text are edited in the block editor, and it supports borders, colors and spacing.
`{site_name}` is replaced with your site's name. The block is only shown to logged-in customers.

### What happens when a customer deletes their account

1. Their active subscriptions (EDD Recurring) are cancelled at the payment gateway, so they're not charged again. If
   that fails, the account isn't deleted, and you're notified.
2. Their license keys (EDD Software Licensing) are detached from the account: they keep working until they expire, and
   can still be renewed with the license key at checkout.
3. WordPress' personal data erasers run (the same as Tools > Erase Personal Data): EDD anonymizes the customer and their
   orders (what was bought, when, for how much and in which country is kept, for taxes), download logs, reviews,
   comments and AffiliateWP customer records. If Brevo for EDD is set up, the contact is deleted from Brevo.
4. The WordPress user is deleted and logged out, and sees a confirmation.
5. You get an email with what was done, and which erasers failed, if any.

Affiliates (AffiliateWP), staff and customers with orders EDD may not change (e.g. still processing) can't delete their
account here: they're asked to contact you.

### Settings

EDD only anonymizes a customer if all of their orders may be anonymized or deleted. Check **Downloads > Settings >
Policies > Privacy > Order Statuses**, e.g. set Renewal to Anonymize.

### Hooks

- `edd_delete_account_customer_roles` (filter): roles of users who can delete their own account.
- `edd_delete_account_blocked_reason` (filter): why a customer can't delete their account (empty if they can).
- `edd_delete_account_notification_recipient` and `edd_delete_account_notification_lines` (filters): the email to you.
- `edd_delete_account_before` and `edd_delete_account_deleted` (actions).
