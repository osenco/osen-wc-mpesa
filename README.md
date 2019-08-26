                                       _
		______   ____    _________| |   _______	   _______      _______	   ______
		|   _ \_/_   | 	|           |  |   __  \   |   ____|  /   ____|   /  __  \
		|  | |  | |  | 	|           |  |  |  |  |  |       |  |       |  |  |  |
		|  | |  | |  | 	| /\	/ \ |  |  |__|  |  |  |____   |  |____   |  |__|  |
		|  | |  | |  | 	|/  \  /   \|  |   ____/   |   ____|  |____   |  |   __   |
		|  | |  | |  | 	|\   \/	   /|  |  |        |  |            |  |  |  |  |  |
		|  | |  | |  | 	| \	  / |  |  |        |  |____    ____|  |  |  |  |  |
		|__| |__| |__|	|  \____/   |  |__|        |_______|  |_______/  |__|  |__|
				|	    |
				|           |
				|___________|
						
# M-PESA For WooCommerce
WordPress Plugin that extends WordPress and WooCommerce functionality to integrate M-PESA for making payments, remittances, checking account balance transaction status and reversals. It also adds Kenyan Counties to the WooCommerce states list.
![Wc M-PESA Configuration](https://user-images.githubusercontent.com/14233942/61905978-04c93980-af33-11e9-93c4-1b1ec6719e66.png)


## Installation
Getting started with M-PESA for WooCommerce is very easy. All configuration is done in the WooCommerce settings in the WordPress admin dashboard.

### Requirements
* Your site/app MUST be running over https for the M-PESA Instant Payment Notification (IPN) to work.
* If you haven't gone through the Go Live process and don't have a production app, you can [generate test credentials here](https://developer.safaricom.co.ke/test_credentials)
* If you wish to integrate C2B Notifications, ensure that validation is activated for your shortcode.

### Auto-installation
* In your WordPress admin, navigate to Plugins and search for M-PESA for WooCommerce.
* Click the install button and the plugin will be installed. Once installed, activate the plugin and configure it at http://yoursite.com/wp-admin/admin.php?page=wc-settings&tab=checkout&section=mpesa

### Manual Installation 
* First, you need to download the latest release of the plugin from [here](https://github.com/osenco/osen-wc-mpesa/releases).
* Using an FTP program, or your hosting control panel, upload the plugin folder (wc-mpesa) to your WordPress installation’s wp-content/plugins/ directory.
* Activate the plugin from the Plugins menu within the WordPress admin

That is all. You are now ready to receive and send money using M-PESA on your WordPress and WooCommerce powered site, for free!

## Contributing
* Fork the repo, do your magic and make a pull request.

### Integration Cost
* Our team of developers are on hand to provide assistance for when you wish to move from Sandbox(test) to Live(production) environment. This assistance is charged a fiat fee of `KSH 4000/$40`

## Acknowledgements
* M-PESA and the M-PESA Logo are registered trademarks of Safaricom PLC
* WordPress and the WordPress logo are registered trademarks of Automattic Inc.
* WooCommerce and the WooCommerce logo are registered trademarks of Automattic Inc.
