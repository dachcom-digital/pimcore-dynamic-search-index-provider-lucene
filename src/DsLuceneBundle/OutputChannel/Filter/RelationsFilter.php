<?php

namespace DsLuceneBundle\OutputChannel\Filter;

use DsLuceneBundle\Configuration\ConfigurationInterface;
use DsLuceneBundle\Service\LuceneStorageBuilder;
use DynamicSearchBundle\EventDispatcher\OutputChannelModifierEventDispatcher;
use DynamicSearchBundle\Filter\FilterInterface;
use DynamicSearchBundle\OutputChannel\RuntimeOptions\RuntimeOptionsProviderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RelationsFilter implements FilterInterface
{
    const VIEW_TEMPLATE_PATH = '@DsLucene/OutputChannel/Filter';

    /**
     * @var array
     */
    protected $options;

    /**
     * @var LuceneStorageBuilder
     */
    protected $storageBuilder;

    /**
     * @var array
     */
    protected $indexProviderOptions;

    /**
     * @var OutputChannelModifierEventDispatcher
     */
    protected $eventDispatcher;

    /**
     * @var RuntimeOptionsProviderInterface
     */
    protected $runtimeOptionsProvider;

    /**
     * @param LuceneStorageBuilder $storageBuilder
     */
    public function __construct(LuceneStorageBuilder $storageBuilder)
    {
        $this->storageBuilder = $storageBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setRequired(['identifier', 'value', 'label', 'relation_label']);
        $resolver->setAllowedTypes('identifier', ['string']);
        $resolver->setAllowedTypes('label', ['string', 'null']);
        $resolver->setAllowedTypes('relation_label', ['closure', 'null']);

        $resolver->setDefaults([
            'relation_label' => null,
            'label'          => null
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function setOptions(array $options)
    {
        $this->options = $options;
    }

    /**
     * {@inheritdoc}
     */
    public function setEventDispatcher(OutputChannelModifierEventDispatcher $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * {@inheritdoc}
     */
    public function setRuntimeParameterProvider(RuntimeOptionsProviderInterface $runtimeOptionsProvider)
    {
        $this->runtimeOptionsProvider = $runtimeOptionsProvider;
    }

    /**
     * {@inheritdoc}
     */
    public function setIndexProviderOptions(array $indexProviderOptions)
    {
        $this->indexProviderOptions = $indexProviderOptions;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsFrontendView(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function enrichQuery($query)
    {
        if (!$query instanceof \Zend_Search_Lucene_Search_Query_Boolean) {
            return $query;
        }

        foreach ($this->runtimeOptionsProvider->getRequestQueryAsArray() as $key => $value) {

            if (substr($key, 0, strlen($this->options['identifier'])) !== $this->options['identifier']) {
                continue;
            }

            $filterTerm = new \Zend_Search_Lucene_Search_Query_MultiTerm();
            $filterTerm->addTerm(new \Zend_Search_Lucene_Index_Term($this->options['value'], $key));
            $query->addSubquery($filterTerm, true);

        }

        return $query;
    }

    /**
     * {@inheritdoc}
     */
    public function buildViewVars($query, $result)
    {
        if (!$query instanceof \Zend_Search_Lucene_Search_Query_Boolean) {
            return null;
        }

        $viewVars = [
            'template' => sprintf('%s/relations.html.twig', self::VIEW_TEMPLATE_PATH),
            'label'    => $this->options['label'],
            'values'   => []
        ];

        $eventData = $this->eventDispatcher->dispatchAction('build_index', [
            'index' => $this->storageBuilder->getLuceneIndex($this->indexProviderOptions['database_name'], ConfigurationInterface::INDEX_BASE_STABLE)
        ]);

        /** @var \Zend_Search_Lucene $index */
        $index = $eventData->getParameter('index');

        $filterNames = [];
        foreach ($index->getFieldNames() as $fieldName) {

            if (substr($fieldName, 0, strlen($this->options['identifier'])) !== $this->options['identifier']) {
                continue;
            }

            $filterNames[] = $fieldName;
        }

        $queryFields = $this->runtimeOptionsProvider->getRequestQueryAsArray();

        $values = [];
        foreach ($filterNames as $filterName) {

            $filterQuery = new \Zend_Search_Lucene_Search_Query_Boolean();
            $filterQuery->addSubquery($query, true);

            $q = new \Zend_Search_Lucene_Search_Query_Term(new\Zend_Search_Lucene_Index_Term($this->options['value'], $filterName));

            $filterQuery->addSubquery($q, true);
            $filterHits = $index->find($filterQuery);

            if (count($filterHits) === 0) {
                continue;
            }

            $active = false;
            if (isset($queryFields[$filterName]) && $queryFields[$filterName] == $this->options['value']) {
                $active = true;
            }

            $relationLabel = null;
            if ($this->options['relation_label'] !== null) {
                $relationLabel = call_user_func($this->options['relation_label'], $filterName);
            } else {
                $relationLabel = $filterName;
            }

            $values[] = [
                'name'           => $filterName,
                'value'          => $this->options['value'],
                'count'          => count($filterHits),
                'active'         => $active,
                'relation_label' => $relationLabel
            ];
        }

        if (count($values) === 0) {
            return null;
        }

        $viewVars['values'] = $values;

        return $viewVars;
    }
}
