# ctrl fields (hidden, starttime, endtime, sys_language_uid, l10n_parent, deleted) are added by the core from TCA
CREATE TABLE tx_shortnrtest_record (
    title varchar(255) DEFAULT '' NOT NULL,
    record_type int(11) DEFAULT '0' NOT NULL
);
