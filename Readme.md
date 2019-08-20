# cps_shortnr

## Installation

- activate the extension in the Extension Manager

## Configuration

- define a `configuration file` in the extension settings
- adjust the `regular expression` to your needs
- you should change the configuration for `pageNotFound_handling` only if really needed and you know what you do

## Configuration file

Default:
```
cps_shortnr {
    decoder.data = register:tx_cpsshortnr_match_1

    encoder {
        field = tx_cpsshortnr_language_parent
        dataWrap = {field:tx_cpsshortnr_identifier_upper}|
        dataWrap.override = {field:tx_cpsshortnr_identifier_upper}|-{field:tx_cpsshortnr_language}
        dataWrap.override.if.isTrue.field = tx_cpsshortnr_language
    }

    # Page
    p {
        source {
            record.data = register:tx_cpsshortnr_match_2
            table = pages
        }
        path {
            typolink {
                parameter.field = uid
                addQueryString = 1
                returnLast = url
                additionalParams.wrap = &L=|
                additionalParams.data = register:tx_cpsshortnr_match_3
                additionalParams.required = 1
            }
        }
    }

    # News
    n {
        source {
            record.data = register:tx_cpsshortnr_match_2
            table = tx_news_domain_model_news
        }
        path {
            typolink {
                parameter = 1
                addQueryString = 1
                additionalParams.cObject = COA
                additionalParams.cObject {
                    10 = TEXT
                    10 {
                        value = &tx_news_pi1[controller]=News&tx_news_pi1[action]=detail&tx_news_pi1[news]={field:uid}
                        insertData = 1
                    }

                    20 = TEXT
                    20 {
                        wrap = &L=|
                        required = 1
                        data = register:tx_cpsshortnr_match_3
                    }
                }
                returnLast = url
            }
        }
    }

    # Internal news
    in < .n
    in.source.encodeMatchFields.type = 1
}
```

### cps_shortnr object

The configuration has to be wrapped in a cps_shortnr object.

#### decoder

| Property | Data type | Description                            |
| ------   | --------- | -------------------------------------- |
| decoder  | stdWrap   | The identifier for the decode process. | 

#### encoder

| Property | Data type | Description                            |
| ------   | --------- | -------------------------------------- |
| encoder  | stdWrap   | The instruction how shortlinks are built. The current record is available as well as four internal fields: *tx_cpsshortnr_identifier_lower* (determined identifier in lower case), *tx_cpsshortnr_identifier_upper* (determined identifier in upper case), *tx_cpsshortnr_language* (either current language or language of the record if available) and *tx_cpsshortnr_language_parent* (either uid of the record or its language parent if available). | 

#### Identifier configuration

The name for an identifier is free to choose. You might have to adopt the `regular expression` for your needs. Please be
aware this should be a short identifier though.

**source**

| Property          | Data type | Description                                       |
| ----------------- | --------- | ------------------------------------------------- |
| record            | stdWrap   | The uid of the record that should be displayed.                                               |
| table             | text      | The table of the record that should be displayed.                                             |
| encodeMatchFields | text      | Additional field => value assignment that the current record has to match for encode process. |

**path**

| Property | Data type | Description                        |
| ------   | --------- | ---------------------------------- |
| path     | stdWrap   | The new Url used for the redirect. | 

## Regular expression

Default:
```
 ([a-zA-Z]+)(\d+)[-]?(\d+)?
```

The regular expression is used to split the incoming Url (shortlink) into different parts. These parts can be used inside
the `identifier configuration`. They are stored in the *TSFE->register* variable with *tx_cpsshortnr_match_* prefix (e.g.
tx_cpsshortnr_match_1, tx_cpsshortnr_match_2).

## PageNotFound_handling

For a detailed description see Install Tool > All configuration > FE > pageNotFound_handling. 

## TypoScript API

Example:
```
lib.shortlink = USER
lib.shortlink {
    userFunc = CPSIT\CpsShortnr\Shortlink\Shortlink->create
    record.data = TSFE:id
    table = pages
}

lib.newslink = USER
lib.newslink {
    userFunc = CPSIT\CpsShortnr\Shortlink\Shortlink->create
    record.data = GP:tx_news_pi1|news
    record.intval = 1
    table = tx_news_domain_model_news
}
```

**userFunc**

| Property | Data type     | Description                                     |
| -------- | ------------- | ----------------------------------------------- |
| userFunc | function name | CPSIT\CpsShortnr\Shortlink\Shortlink->create    |
| record   | stdWrap       | The uid of the record that should be encoded.   |
| table    | text          | The table of the record that should be encoded. |
