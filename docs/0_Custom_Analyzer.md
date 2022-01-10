# Custom Analyzer
We're using the `CaseInsensitive` Analyzer by default. But it's also possible to use a custom analyzer.
A Analyzer is used on indexing and query time.

### Listener
```yaml
App\EventListener\LuceneAnalyzerListener:
    autowire: true
    public: false
    tags:
        - { name: kernel.event_listener, event: ds_lucene.analyzer.build, method: build }
```

### Service
```php
<?php

namespace App\EventListener;

use DsLuceneBundle\Event\AnalzyerEvent;

class LuceneAnalyzerListener
{
    /**
     * @param AnalzyerEvent $event
     */
    public function build(AnalzyerEvent $event)
    {
        $analyzer = new MyAwesomeListener();
        $event->setAnalyzer($analyzer);
    }
}
```

*** 

## Syllable Analyzer

The Lucene Data Provider Bundles comes with a pre-configured Syllable Analyzer, which is **disabled by default**.
The Syllable Analyzer allows you to split your indexable strings into reasonable chunks, based on a given locale.
To achive this, we're using the [OrgHeiglHyphenator Library](https://github.com/heiglandreas/Org_Heigl_Hyphenator) library ([Documentation](https://orgheiglhyphenator.readthedocs.io/en/latest/)).

### Benefits / Workflow:

Example Data: `We have some really long words in german like sauerstofffeldflasche` 

1. **Split Data**: `We have some re al ly long words in ger man like sau er stoff feld fla sche.`
2. **Add Original Data**: `We have some really long words in german like sauerstofffeldflasche We have some re al ly long words in ger man like sau er stoff feld fla sche.` 
3. **Final Unique Data**: `We have some really long words in german like sauerstofffeldflasche re al ly ger man sau er stoff feld fla sche.`
4. Send Data to Tokenizer. If you also have enabled the [stopwords filter](./1_CustomTokenFilter.md), you can get rid of unnecessary words like `er` or `re` too.

### Setup

First, you need to install the library
```bash
$ composer require org_heigl/hyphenator:^2.3
```

## Listener
Now, add the listener (basically the same as described above)

```yaml
App\EventListener\LuceneSyllableAnalyzerListener:
    autowire: true
    public: false
    tags:
        - { name: kernel.event_listener, event: ds_lucene.analyzer.build, method: build }
```

### Service
Finally, add the service:

```php
<?php

namespace App\EventListener;

use Org\Heigl\Hyphenator;
use DsLuceneBundle\Event\AnalzyerEvent;
use DsLuceneBundle\Lucene\Analyzer\Syllable;

class LuceneSyllableAnalyzerListener
{
    /**
     * @param AnalzyerEvent $event
     */
    public function build(AnalzyerEvent $event)
    {
        // 
        // Important thing here!
        //
        // Use the syllable analyzer on indexing time only!
        // we don't want/need to tokenize a users input on query time
        // returning nothing triggers the default fallback => CaseInsensitive Analyzer 

        if (!$event->isIndexMode()) {
            return;
        }

        $options = new Hyphenator\Options();

        $options->setHyphen(' ')
            ->setDefaultLocale($this->checkLocale($event->getLocale()))
            ->setRightMin(4)
            ->setLeftMin(2)
            ->setWordMin(5)
            ->setFilters('NonStandard')
            ->setTokenizers(['Whitespace', 'Punctuation']);

        $analyzer = new Syllable();
        
        // if you don't define any options, some default options will be generated
        $analyzer->setHyphenatorOptions($options);

        $event->setAnalyzer($analyzer);
    }

    /**
     * @param $locale
     *
     * @return string
     */
    protected function checkLocale($locale)
    {
        if (empty($locale)) {
            return 'en';
        }

        switch ($locale) {
            case 'de':
                return 'de_DE';
            case 'it':
                return 'it_IT';
            default:
                return $locale;
        }
    }
}
```
