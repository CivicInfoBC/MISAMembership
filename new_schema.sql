/*
 *	Set engine to InnoDB
 */
SET storage_engine=INNODB;


DROP DATABASE IF EXISTS `misa_membership`;


/*
 *	Create database.
 *
 *	"utf8mb4" encapsulates all of
 *	Unicode, unlike the older
 *	"utf8".
 */
CREATE DATABASE `misa_membership`
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;


USE `misa_membership`;


/*
 *	System options.
 *
 *	Key/value pair store.
 */
CREATE TABLE `settings` (
	`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
	`key` varchar(191) NOT NULL,
	`value` text NOT NULL,
	PRIMARY KEY (`id`),
	INDEX (`key`)
);


/*
 *	Membership types.
 */
CREATE TABLE `membership_types` (
	`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
	`name` varchar(255) NOT NULL,
	`description` text,
	`price` decimal(11,2) NOT NULL DEFAULT '0.00',
	`is_municipality` bool NOT NULL DEFAULT '0',
	PRIMARY KEY (`id`)
);


/*
 *	Organizations table.
 */
CREATE TABLE `organizations` (
	`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
	`name` varchar(255) NOT NULL,
	`address1` varchar(255) DEFAULT NULL,
	`address2` varchar(255) DEFAULT NULL,
	`city` varchar(255) DEFAULT NULL,
	`postal_code` varchar(16) DEFAULT NULL,
	`territorial_unit` char(255) DEFAULT 'British Columbia',
	`country` varchar(64) DEFAULT 'Canada',
	`phone` varchar(50) DEFAULT NULL,
	`fax` varchar(50) DEFAULT NULL,
	`url` varchar(255) DEFAULT NULL,
	--	Is this optional?  Should it be nullable?
	--	Existing database appears to use zero
	--	as faux NULL, (which will not work
	--	at all with a foreign key constraint)
	--	but zero is a valid AUTO_INCREMENT
	--	ID value so if it's necessary for
	--	organizations to not have a membership
	--	type this should be nullable.
	`membership_type_id` int(11) unsigned NOT NULL,
	`contact_name` varchar(255) DEFAULT NULL,
	`contact_title` varchar(255) DEFAULT NULL,
	`contact_email` varchar(255) DEFAULT NULL,
	`contact_phone` varchar(50) DEFAULT NULL,
	`contact_fax` varchar(50) DEFAULT NULL,
	`secondary_contact_name` varchar(255) DEFAULT NULL,
	`secondary_contact_title` varchar(255) DEFAULT NULL,
	`secondary_contact_email` varchar(255) DEFAULT NULL,
	`secondary_contact_phone` varchar(50) DEFAULT NULL,
	`secondary_contact_fax` varchar(50) DEFAULT NULL,
	--	When this field is set to true (i.e. 1)
	--	the organization's members shall not be
	--	barred from logging in if the organization
	--	has not paid this calendar year.
	`perpetual` bool NOT NULL DEFAULT '0',
	--	If this field is set to not-true (i.e. NULL
	--	or 0) the organizations members shall be
	--	barred from logging in.
	`enabled` bool DEFAULT NULL,
	PRIMARY KEY (`id`),
	FOREIGN KEY (`membership_type_id`) REFERENCES `membership_types`(`id`)
	--	Consider adding:
	--
	--		gst_exempt
	--		gst_number
);


/*
 *	Membership year table.
 *
 *	Allows information to be attached
 *	to a membership year, and allows
 *	membership years to have special
 *	meanings, like '2012-2013' rather
 *	than being constrained to a specific
 *	year.
 */
CREATE TABLE `membership_years` (
	`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
	`name` varchar(50) NOT NULL,
	`start` datetime NOT NULL,
	`end` datetime NOT NULL,
	PRIMARY KEY (`id`)
);


/*
 *	Organizations payment table.
 */
CREATE TABLE `payment` (
	`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
	--	This is the invoice number from the existing
	--	payment database.
	`invoice` int(11) unsigned DEFAULT NULL,
	`org_id` int(11) unsigned NOT NULL,
	`membership_type_id` int(11) unsigned,
	`membership_year_id` int(11) unsigned NOT NULL,
	`type` enum(
		'membership renewal',
		'membership new',
		'sponsor',
		'conference',
		'other'
	) NOT NULL,
	`created` datetime NOT NULL,
	`subtotal` decimal(11,2) NOT NULL,
	`tax` decimal(11,2) NOT NULL,
	`total` decimal(11,2) NOT NULL,
	`paid` bool NOT NULL DEFAULT '0',
	`cardname` varchar(100) DEFAULT NULL,
	`paymethod` varchar(50) DEFAULT NULL,
	`chequeissuedby` varchar(50) DEFAULT NULL,
	`chequenumber` varchar(50) DEFAULT NULL,
	`amountpaid` decimal(11,2) DEFAULT NULL,
	`datepaid` datetime DEFAULT NULL,
	`notes` text DEFAULT NULL,
	PRIMARY KEY (`id`),
	FOREIGN KEY (`org_id`) REFERENCES `organizations`(`id`),
	FOREIGN KEY (`membership_type_id`) REFERENCES `membership_types`(`id`),
	FOREIGN KEY (`membership_year_id`) REFERENCES `membership_years`(`id`)
);


/*
 *	Users table.
 */
CREATE TABLE `users` (
	`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
	`first_name` varchar(255) NOT NULL,
	`last_name` varchar(255) NOT NULL,
	`email` varchar(127) NOT NULL,
	`title` varchar(255) DEFAULT NULL,
	--	Can implement a login system that uses
	--	`username` AND `email` as a username since
	--	they're both necessarily unique.
	`username` varchar(127) NOT NULL,
	--	This field will store MD5 hashes, and
	--	the next time a user logs in successfully
	--	(and therefore the system has their password
	--	in plaintext) the system will generate them
	--	a new, secure, bcrypt password hash with
	--	salt and replace the MD5 hash stored here.
	`password` char(60) binary NOT NULL,
	`address` varchar(255) DEFAULT NULL,
	`address2` varchar(255) DEFAULT NULL,
	`city` varchar(255) DEFAULT NULL,
	`territorial_unit` varchar(255) DEFAULT NULL,
	`postal_code` varchar(16) DEFAULT NULL,
	`country` varchar(64) DEFAULT NULL,
	`phone` varchar(50) DEFAULT NULL,
	`cell` varchar(50) DEFAULT NULL,
	`fax` varchar(50) DEFAULT NULL,
	--	If a user exists with `org_id` NULL then
	--	they shall be unliked from an organization,
	--	and shall always be able to login unless
	--	`enabled` is set to 0.
	`org_id` int(11) unsigned DEFAULT NULL,
	`activation_key` varchar(32) DEFAULT NULL,
	--	If set to 0 this user shall be unable to
	--	login regardless of their organization's
	--	status
	`enabled` bool NOT NULL DEFAULT '1',
	--	Specifies the privileges that the user
	--	shall have:
	--
	--	'user'		May edit their own information.
	--	'superuser'	May edit their own information and
	--				their organization's information.
	--				May elevate members of their own
	--				organization to 'superuser'.
	--	'admin'		May do everything.
	`type` enum('user','superuser','admin') NOT NULL DEFAULT 'user',
	PRIMARY KEY (`id`),
	FOREIGN KEY (`org_id`) REFERENCES `organizations`(`id`),
	UNIQUE KEY (`email`),
	UNIQUE KEY (`username`),
	INDEX (`type`)
);


/*
 *	API keys table.
 *
 *	Contains the keys that API users
 *	will have to supply to access the
 *	API.
 *
 *	Maybe we want to add a field that
 *	controls the kind of access people
 *	querying with this API key have?
 */
CREATE TABLE `api_keys` (
	--	128-bit key
	`key` char(32) binary NOT NULL,
	--	This can be null since we
	--	may want to have orgs that
	--	aren't members have a key
	--
	--	We may also want a given org
	--	to have more than one key,
	--	so putting this in the orgs
	--	table would be inadequate
	`org_id` int(11) unsigned,
	`notes` text,
	PRIMARY KEY (`key`),
	FOREIGN KEY (`org_id`) REFERENCES `organizations`(`id`)
);


/*
 *	Allows API keys to be quickly
 *	checked.
 */
CREATE INDEX `api_key_index` ON `api_keys`(`key`);


/*
 *	Allows users to have long-lived
 *	login sessions.
 */
CREATE TABLE `sessions` (
	--	128-bit key
	`key` char(32) binary NOT NULL,
	`user_id` int(11) unsigned NOT NULL,
	--	IP address the session
	--	was created from
	`created_ip` varchar(45) NOT NULL,
	--	Date and time the session
	--	was created
	`created` datetime NOT NULL,
	--	IP the session was last seen
	--	from
	`last_ip` varchar(45) NOT NULL,
	--	Date and time the session
	--	was last seen
	`last` datetime NOT NULL,
	--	When the session expires
	`expires` datetime NOT NULL,
	PRIMARY KEY (`key`),
	FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
);


/*
 *	Test user
 *
 *	Password is "password", this test
 *	user uses the old, insecure hashing
 *	method to test the automatic
 *	switch over mechanism.
 */
/*INSERT INTO `users` (
	`first_name`,
	`last_name`,
	`email`,
	`title`,
	`username`,
	`password`,
	`enabled`,
	`type`
) VALUES (
	'Robert',
	'Leahy',
	'rleahy@civicinfo.bc.ca',
	'Web Designer',
	'rleahy',
	'5f4dcc3b5aa765d61d8327deb882cf99',
	'1',
	'admin'
);*/


/*
 *	This setting determines which users will
 *	receive notifications for new membership
 *	applications.
 *
 *	This setting can be plural, i.e. the key
 *	may occur many times with many different
 *	e-mail addresses, in which case the notification
 *	will be sent to each of them.
 */
INSERT INTO `settings` (
	`key`,
	`value`
) VALUES (
	'membership_application_email',
	'rleahy@rleahy.ca'
);


/*
 *	This setting determines whether membership
 *	application shall be open or closed.
 *
 *	This setting is singular, i.e. only the first
 *	occurrence of the key (the order in which they
 *	occur being possibly implementation-defined or
 *	unspecified) will be regarded.
 *
 *	If set to "true" the application will be open,
 *	all other values (or the absence of any value)
 *	will be regarded as "false".
 */
INSERT INTO `settings` (
	`key`,
	`value`
) VALUES (
	'membership_application_open',
	'true'
);


/*
 *	This setting determines whether membership
 *	dues may be paid.
 *
 *	This setting is singular, i.e. only the first
 *	occurrence of the key (the order in which they
 *	occur being possibly implementation-defined or
 *	unspecified) will be regarded.
 *
 *	If set to "true" membership dues may be paid
 *	for all membership years which are:
 *
 *	1.	Not in the past.
 *	2.	In the `membership_years` table.
 */
INSERT INTO `settings` (
	`key`,
	`value`
) VALUES (
	'dues_payment_permitted',
	'true'
);