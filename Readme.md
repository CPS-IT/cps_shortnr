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
}
```

### cps_shortnr object

The configuration has to be wrapped in a cps_shortnr object.

**decoder**

| Property | Data type | Description                            |
| ------   | --------- | -------------------------------------- |
| decoder  | stdWrap   | The identifier for the decode process. | 

#### Identifier configuration

The name for an identifier is free to choose. You might have to adopt the `regular expression` for your needs. Please be
aware this should be a short identifier though.

**source**

| Property | Data type | Description                                       |
| ------   | --------- | ------------------------------------------------- |
| record   | stdWrap   | The uid of the record that should be displayed.   |
| table    | stdWrap   | The table of the record that should be displayed. |

**path**

| Property | Data type | Description                        |
| ------   | --------- | ---------------------------------- |
| path     | stdWrap   | The new Url used for the redirect. | 

## Regular expression

Default:
```
([a-zA-Z]+)(\d+)(-(\d+))?
```

The regular expression is used to split the incoming Url (shortlink) into different parts. These parts can be used inside
the `identifier configuration`. They are stored in the *TSFE->register* variable with *tx_cpsshortnr_match_* prefix (e.g.
tx_cpsshortnr_match_1, tx_cpsshortnr_match_2).

## PageNotFound_handling

For a detailed description see Install Tool > All configuration > FE > pageNotFound_handling. 
