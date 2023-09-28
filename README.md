# Dynamic Search | Index Provider: PHP Lucene

[![Software License](https://img.shields.io/badge/license-GPLv3-brightgreen.svg?style=flat-square)](LICENSE.md)
[![Latest Release](https://img.shields.io/packagist/v/dachcom-digital/dynamic-search-index-provider-lucene.svg?style=flat-square)](https://packagist.org/packages/dachcom-digital/dynamic-search-index-provider-lucene)
[![Tests](https://img.shields.io/github/actions/workflow/status/dachcom-digital/pimcore-dynamic-search-index-provider-lucene/.github/workflows/codeception.yml?branch=master&style=flat-square&logo=github&label=codeception)](https://github.com/dachcom-digital/pimcore-dynamic-search-index-provider-lucene/actions?query=workflow%3ACodeception+branch%3Amaster)
[![PhpStan](https://img.shields.io/github/actions/workflow/status/dachcom-digital/pimcore-dynamic-search-index-provider-lucene/.github/workflows/php-stan.yml?branch=master&style=flat-square&logo=github&label=phpstan%20level%204)](https://github.com/dachcom-digital/pimcore-dynamic-search-index-provider-lucene/actions?query=workflow%3A"PHP+Stan"+branch%3Amaster)

An index storage extension for [Pimcore Dynamic Search](https://github.com/dachcom-digital/pimcore-dynamic-search).
Store data with the PHP Lucene index service.

## Release Plan
| Release | Supported Pimcore Versions | Supported Symfony Versions | Release Date | Maintained     | Branch                                                                                          |
|---------|----------------------------|----------------------------|--------------|----------------|-------------------------------------------------------------------------------------------------|
| **3.x** | `11.0`                     | `^6.2`                     | 28.09.2023   | Feature Branch | master                                                                                          |
| **2.x** | `10.0` -  `10.6`           | `^5.4`                     | 19.12.2021   | No             | [2.x](https://github.com/dachcom-digital/pimcore-dynamic-search-index-provider-lucene/tree/2.x) |
| **1.x** | `6.6` - `6.9`              | `^4.4`                     | 18.04.2021   | No             | [1.x](https://github.com/dachcom-digital/pimcore-dynamic-search-index-provider-lucene/tree/1.x) |

***

## Installation  
```json
"require" : {
    "dachcom-digital/dynamic-search" : "~3.0.0",
    "dachcom-digital/dynamic-search-index-provider-lucene" : "~3.0.0"
}
```

### Dependencies
This package will also install a fork of [ZendSearch](https://github.com/dachcom-digital/ZendSearch) to provide the latest PHP compatibility.

### Dynamic Search Bundle
You need to install / enable the Dynamic Search Bundle first.
Read more about it [here](https://github.com/dachcom-digital/pimcore-dynamic-search#installation).
After that, proceed as followed:

Add Bundle to `bundles.php`:
```php
<?php

return [
    \DsLuceneBundle\DsLuceneBundle::class => ['all' => true],
];
```

***

## Basic Setup

```yaml

dynamic_search:
    context:
        default:
            index_provider:
                service: 'lucene'
                options:
                    database_name: 'my_lucene_storage'
```

***

## Provider Options

| Name                    | Default Value | Description |
|:------------------------|:--------------|:------------|
| `database_name`         | null          |             |
| `force_adding_document` | true          |             |
| `analyzer`              | []            |             |

***

## Index Fields
**Available Index Fields**:   

| Name        | Description                                                                                                                                                                                                                                                       |
|:------------|:------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `binary`    | Binary fields are not tokenized or indexed, but are stored for retrieval with search hits. They can be used to store any data encoded as a binary string, such as an image icon.                                                                                  |
| `keyword`   | Keyword fields are stored and indexed, meaning that they can be searched as well as displayed in search results. They are not split up into separate words by tokenization.                                                                                       |
| `text`      | Text fields are stored, indexed, and tokenized. Text fields are appropriate for storing information like subjects and titles that need to be searchable as well as returned with search results.                                                                  |
| `unIndexed` | UnIndexed fields are not searchable, but they are returned with search hits. Database timestamps, primary keys, file system paths, and other external identifiers are good candidates for UnIndexed fields.                                                       |
| `unStored`  | UnStored fields are tokenized and indexed, but not stored in the index. Large amounts of text are best indexed using this type of field. Storing data creates a larger index on disk, so if you need to search but not redisplay the data, use an UnStored field. |

***

## Output Channel Services

### Autocomplete
**Identifier**: `lucene_autocomplete`   
**Available Options**:   

| Name                             | Default Value | Description |
|:---------------------------------|:--------------|:------------|
| `min_prefix_length`              | 3             |             |
| `use_fuzzy_term_search_fallback` | true          |             |
| `fuzzy_default_prefix_length`    |               |             |
| `fuzzy_similarity`               | 0.5           |             |

### Suggestions
**Identifier**: `lucene_suggestions`   
**Available Options**:   

| Name                              | Default Value | Description |
|:----------------------------------|:--------------|:------------|
| `min_prefix_length`               | 3             |             |
| `result_limit`                    | 10            |             |
| `only_last_word_wildcard`         | false         |             |
| `multiple_words_operator`         | 'OR'          |             |
| `restrict_search_fields`          | []            |             |
| `restrict_search_fields_operator` | 'OR'          |             |

### Search
**Identifier**: `lucene_search`   
**Available Options**:   

| Name                | Default Value | Description |
|:--------------------|:--------------|:------------|
| `min_prefix_length` | 3             |             |
| `max_per_page`      | 10            |             |

### Multi Search
**Identifier**: `lucene_multi_search`   
**Available Options**: none

***

## Filter

### RelationsFilter
**Identifier**: `relations`   
**Available Options**:   

| Name               | Default Value | Allowed Type  | Description |
|:-------------------|:--------------|:--------------|:------------|
| `identifier`       | null          | string        |             |
| `value`            | null          | string        |             |
| `label`            | null          | string, null  |             |
| `show_in_frontend` | true          | bool          |             |
| `relation_label`   | null          | closure, null |             |


## Output Normalizer
A Output Normalizer can be defined for each output channel.

### lucene_document_key_value_normalizer

**Available Options**:   

| Name          | Default Value | Description |
|---------------|---------------|-------------|
| `skip_fields` | []            |             |

## Further Information
- Lucene Configuration
    - [Custom Analyzer](./docs/0_Custom_Analyzer.md) (Example: Syllable Analyzer)
    - [Lucene Token Filter](./docs/1_CustomTokenFilter.md) (Stemming Filter, Stop Words Filter)    
    - [Debugging](./docs/2_Debugging.md) (Debugging Lucene Index Database)

***

## Copyright and License
Copyright: [DACHCOM.DIGITAL](http://dachcom-digital.com)  
For licensing details please visit [LICENSE.md](./LICENSE.md)

## Upgrade Info
Before updating, please [check our upgrade notes!](./UPGRADE.md)  
