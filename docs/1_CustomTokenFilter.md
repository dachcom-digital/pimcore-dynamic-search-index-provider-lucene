# Custom Token Filter
Add some token filter like stopwords or a stemming filter. By default, **no** filter is enabled.
This Bundles comes with two preconfigured filters.

- Stop Words
- Stemmer

But you're also able to add your custom filter. But first let's checkout, how to enable the preconfigured filters:

## Stop Words

### Configuration
You need to add the `stop_words` block to `filter`, to enable it.
There is no further configuration required. You're able to define your own stopwords library, if you want to.

```yaml
dynamic_search:
    context:
        default:
            index_provider:
                service: 'lucene'
                options:
                    database_name: 'default'
                    analyzer:
                        # forced_locale: de
                        filter:
                            stop_words:
                                on_index_time: true
                                on_query_time: false
                                libraries:
                                    -   locale: de
                                        file: '%%dsl_stop_words_lib_path%%/de'
                                    -   locale: fr
                                        file: '%%dsl_stop_words_lib_path%%/fr'
                                    -   locale: it
                                        file: '%%dsl_stop_words_lib_path%%/it'
                                    -   locale: en
                                        file: '%%dsl_stop_words_lib_path%%/en'
```

### Options
| Name                             | Default Value | Description |
|:---------------------------------|:--------------|:------------|
|`on_index_time`                   | true          | Enabled filter on index time |
|`on_query_time`                   | true          | Enabled filter on query time |
|`libraries`                       | array         | Set a custom library for each locale. `dsl_stop_words_lib_path` is a default path to our internal library |


## Stemmer

### Configuration
You need to add the `stemming` block to `filter`, to enable it.
There is no further configuration required. You're able to choose your own stemmer class, if you want to.

```yaml
dynamic_search:
    context:
        default:
            index_provider:
                service: 'lucene'
                options:
                    database_name: 'default'
                    analyzer:
                        # forced_locale: de
                        filter:
                            stemming:
                                on_index_time: true
                                on_query_time: true
                                locale_aware: true
                                class: '\DsLuceneBundle\Lucene\Filter\Stemming\SnowBallStemmingFilter'

```

### Options
| Name                             | Default Value | Description |
|:---------------------------------|:--------------|:------------|
|`on_index_time`                   | true          | Enabled filter on index time |
|`on_query_time`                   | true          | Enabled filter on query time |
|`locale_aware`                    | false         | If set to `true`, a locale is required, otherwise the stemmer will be skipped |
|`class`                           | `'\DsLuceneBundle\Lucene\Filter\Stemming\SnowBallStemmingFilter'` | The SnowBall Stemmer per default |


## Custom Filter
Add your custom filter

### Configuration
Add your custom filter block to `filter`, to enable it.

```yaml
dynamic_search:
    context:
        default:
            index_provider:
                service: 'lucene'
                options:
                    database_name: 'default'
                    analyzer:
                        # forced_locale: de
                        filter:
                            my_custom_filter:
                                on_index_time: true
                                on_query_time: true
                                locale_aware: true
                                class: 'App\Lucene\Filter\MyFilter\MyFilterClass'

```

### Options
| Name                             | Default Value | Description |
|:---------------------------------|:--------------|:------------|
|`on_index_time`                   | true          | Enabled filter on index time |
|`on_query_time`                   | true          | Enabled filter on query time |
|`locale_aware`                    | false         | If set to `true`, a locale is required, otherwise the filter will be skipped |
|`class`                           | null          | Set your custom filter class |

### Analyzer Configuration
You may have noticed the `forced_locale` flag. If you don't have multiple languages, just define a default one.

| Name                             | Default Value | Description |
|:---------------------------------|:--------------|:------------|
|`forced_locale`                   | null          | Use a default locale like `en` |

