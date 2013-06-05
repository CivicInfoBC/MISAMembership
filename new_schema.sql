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
	`org_name` varchar(255) NOT NULL,
	`address1` varchar(255) NOT NULL,
	--	Existing database does not have
	--	this field nullable, but most
	--	results do not have this field,
	--	setting it to empty string,
	--	therefore it should probably be
	--	nullable since no data is an
	--	acceptable state for it
	`address2` varchar(255) DEFAULT NULL,
	`city` varchar(255) NOT NULL,
	`postal_code` varchar(16) NOT NULL,
	`province` char(2) NOT NULL DEFAULT 'BC',
	`country` varchar(30) NOT NULL DEFAULT 'Canada',
	`org_phone` varchar(50) NOT NULL,
	--	Is this optional?  Should it be nullable?
	--	Existing database appears to use zero
	--	as faux NULL, (which will not work
	--	at all with a foreign key constraint)
	--	but zero is a valid AUTO_INCREMENT
	--	ID value so if it's necessary for
	--	organizations to not have a membership
	--	type this should be nullable.
	`membership_type_id` int(11) unsigned NOT NULL,
	`contact_name` varchar(255) NOT NULL,
	`contact_title` varchar(255) NOT NULL,
	`contact_email` varchar(255) NOT NULL,
	`contact_phone` varchar(50) NOT NULL,
	--	What does this field actually mean?
	--	Jason mentioned the ability to have
	--	organizations flagged as perpetual,
	--	but I'm not sure what, exactly, that
	--	means.
	`perpetual` bool NOT NULL DEFAULT '0',
	PRIMARY KEY (`id`),
	FOREIGN KEY (`membership_type_id`) REFERENCES `membership_types`(`id`)
	--	Existing table has the following fields:
	--
	--		enabled
	--		paymentStatus
	--		gst_exempt
	--		gst_number
	--		applicationDateTime
	--		lastModified
	--		lastModifiedByManager
	--
	--	are these no longer needed or was
	--	their exclusion an oversight?
);


/*
 *	Organizations payment table.
 */
CREATE TABLE `payment` (
	`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
	`org_id` int(11) unsigned NOT NULL,
	`membership_type_id` int(11) unsigned NOT NULL,
	`membership_year` varchar(4) NOT NULL,
	`created` date NOT NULL,
	`subtotal` decimal(11,2) NOT NULL,
	`total` decimal(11,2) NOT NULL,
	`paid` bool NOT NULL DEFAULT '0',
	`cardname` varchar(100) DEFAULT NULL,
	`paymethod` varchar(50) DEFAULT NULL,
	`chequeissuedby` varchar(50) DEFAULT NULL,
	`chequenumber` varchar(50) DEFAULT NULL,
	`amountpaid` decimal(11,2) DEFAULT NULL,
	`datepaid` date DEFAULT NULL,
	`notes` text DEFAULT NULL,
	PRIMARY KEY (`id`),
	FOREIGN KEY (`org_id`) REFERENCES `organizations`(`id`),
	FOREIGN KEY (`membership_type_id`) REFERENCES `membership_types`(`id`)
);


/*
 *	Domains table.
 */
CREATE TABLE `domains` (
	`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
	`org_id` int(11) unsigned NOT NULL,
	`name` varchar(123) NOT NULL,
	PRIMARY KEY (`id`),
	FOREIGN KEY (`org_id`) REFERENCES `organizations`(`id`),
	UNIQUE KEY (`org_id`,`name`)
);


/*
 *	Users table.
 */
CREATE TABLE `users` (
	`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
	`first_name` varchar(255) NOT NULL,
	`last_name` varchar(255) NOT NULL,
	`email` varchar(127) NOT NULL,
	`title` varchar(255) NOT NULL,
	--	Added `username` and `password` from
	--	old users table, if they're not needed
	--	they can be removed.
	`username` varchar(127) NOT NULL,
	--	The old database schema/login system
	--	hashes passwords using the MD5 hash
	--	algorithm with no salt.
	--
	--	MD5 is now considered cryptographically
	--	insecure, and without salting attacking
	--	the passwords is trivial.
	--
	--	bcrypt is considered a secure hashing
	--	scheme, purpose-built for password
	--	hashing.
	--
	--	However, all existing passwords are stored
	--	as MD5 hashes.
	--
	--	Therefore I propose a transition to using
	--	bcrypt.  bcrypt hashes begin with a very
	--	particular character string, and therefore
	--	can be differentiated from MD5 hashes.
	--
	--	This field will store MD5 hashes, and
	--	the next time a user logs in successfully
	--	(and therefore the system has their password
	--	in plaintext) the system will generate them
	--	a new, secure, bcrypt password hash with
	--	salt and replace the MD5 hash stored here.
	`password` char(60) binary NOT NULL,
	--	Why is this optional here but
	--	not in organizations?
	`address` varchar(255) DEFAULT NULL,
	`address2` varchar(255) DEFAULT NULL,
	`city` varchar(255) DEFAULT NULL,
	`province` varchar(64) DEFAULT NULL,
	`postal_code` varchar(16) DEFAULT NULL,
	`country` varchar(64) DEFAULT NULL,
	`phone` varchar(50) DEFAULT NULL,
	`cell` varchar(50) DEFAULT NULL,
	--	Can users exist without being
	--	paired with an organization?
	--	I.e. should this really be
	--	nullable?
	`org_id` int(11) unsigned DEFAULT NULL,
	--	What is this field for?
	`activation_key` varchar(128) DEFAULT NULL,
	`enabled` bool NOT NULL DEFAULT '0',
	`subscribed` bool NOT NULL DEFAULT '0',
	`admin` bool NOT NULL DEFAULT '0',
	PRIMARY KEY (`id`),
	FOREIGN KEY (`org_id`) REFERENCES `organizations`(`id`),
	--	Why is this necessarily unique?
	--	Are usernames being abandoned
	--	in favour of username-based
	--	login?
	UNIQUE KEY (`email`),
	UNIQUE KEY (`username`)
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
INSERT INTO `users` (
	`first_name`,
	`last_name`,
	`email`,
	`title`,
	`username`,
	`password`,
	`enabled`
) VALUES (
	'Robert',
	'Leahy',
	'rleahy@civicinfo.bc.ca',
	'Web Designer',
	'rleahy',
	'5f4dcc3b5aa765d61d8327deb882cf99',
	'1'
);