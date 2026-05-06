# WooCommerce Birthday Marketing Plugin

A WordPress plugin that automatically sends personalized birthday discount emails to registered customers, with a customizable popup registration form and full customer management panel.

---

## Features

- **Registration popup** — Appears automatically on page load with a customizable image, title and form
- **Birthday email automation** — Sends a personalized email with a unique discount coupon on the customer's birthday every year
- **Unique WooCommerce coupons** — Automatically generates a 10% discount coupon per customer, single-use, valid for 30 days, restricted to their email
- **Customer management panel** — View, search and delete registered contacts directly from the WordPress admin
- **CSV export & import** — Export all contacts to Excel-compatible CSV and import contacts in bulk
- **Popup image settings** — Store owner can update the popup image from the WordPress media library without touching code
- **Anti-abuse protection** — Validates unique email and phone number per registration
- **Cookie-based popup** — Popup appears once every 30 days per visitor to avoid being intrusive
- **SMTP compatible** — Works with any WordPress SMTP plugin (WP Mail SMTP, Post SMTP, etc.)

---

## Requirements

- WordPress 5.0+
- WooCommerce 4.0+
- PHP 7.4+

---

## Installation

1. Download the plugin file `woocommerce-birthday-marketing.php`
2. In your WordPress admin go to **Plugins → Add New → Upload Plugin**
3. Upload the file and click **Install Now**
4. Click **Activate Plugin**

Or manually:

1. Copy the file to `/wp-content/plugins/woocommerce-birthday-marketing/`
2. Activate from **Plugins** in the WordPress admin

---

## Configuration

### 1. Add the registration form to a page (optional)
If you want the form accessible as a standalone page, create a new page and add the shortcode:
```
[formulario_cumpleanos]
```

### 2. Set the popup image
Go to **Cumpleaños → Ajustes** in the WordPress admin and select an image from your media library. This image appears on the left side of the registration popup.

### 3. Configure SMTP (recommended)
Install any SMTP plugin (WP Mail SMTP, Post SMTP) and configure it with your email provider credentials so birthday emails are delivered reliably and don't land in spam.

### 4. Set up a server cron (recommended)
For reliable daily execution independent of site traffic, add a cron job in your hosting panel:

```
0 9 * * * curl https://yoursite.com/wp-cron.php?doing_wp_cron
```

This ensures birthday emails are sent every day at 9:00 AM regardless of site visits.

---

## How it works

1. A visitor fills in the registration form (name, phone, email, date of birth)
2. Their data is stored in a custom database table
3. Every day at 9:00 AM the plugin checks for customers whose birthday is today
4. A unique WooCommerce coupon is generated for each customer
5. A personalized birthday email is sent with the coupon code
6. The coupon is valid for 30 days, single use, and restricted to that customer's email

---

## Customer Management

Go to **Cumpleaños 🎂** in the WordPress admin to:

- View all registered contacts
- Search by name, email or phone
- Delete individual contacts
- Export all contacts to CSV
- Import contacts from CSV

---

## Coupon Details

Each birthday coupon is automatically configured with:

| Setting | Value |
|---|---|
| Discount type | Percentage |
| Amount | 10% |
| Usage limit | 1 |
| Expiry | 30 days from birthday |
| Email restriction | Customer's registered email only |

---

## Screenshots

> Add screenshots of the popup, the admin panel and a sample birthday email here.

---

## License

GPL-2.0+

---

## Author

Built with ❤️ for small WooCommerce stores.
