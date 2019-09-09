<?php

namespace DsLuceneBundle\OutputChannel\Filter;

use DsLuceneBundle\Configuration\ConfigurationInterface;
use DsLuceneBundle\Exception\LuceneException;
use DsLuceneBundle\Service\LuceneStorageBuilder;
use DynamicSearchBundle\EventDispatcher\OutputChannelModifierEventDispatcher;
use DynamicSearchBundle\Filter\FilterInterface;
use DynamicSearchBundle\OutputChannel\Context\OutputChannelContextInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use ZendSearch\Lucene;
use ZendSearch\Lucene\Search\Query;
use ZendSearch\Lucene\Search\QueryParser;

class RelationsFilter implements FilterInterface
{
    const VIEW_TEMPLATE_PATH = '@DsLucene/OutputChannel/Filter';

    /**
     * @var array
     */
    protected $options;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var LuceneStorageBuilder
     */
    protected $storageBuilder;

    /**
     * @var OutputChannelContextInterface
     */
    protected $outputChannelContext;

    /**
     * @var OutputChannelModifierEventDispatcher
     */
    protected $eventDispatcher;

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
        $resolver->setRequired(['identifier', 'value', 'label', 'show_in_frontend', 'relation_label', 'query_behaviour']);
        $resolver->setAllowedTypes('show_in_frontend', ['bool']);
        $resolver->setAllowedValues('query_behaviour', ['main_query', 'sub_query']);
        $resolver->setAllowedTypes('identifier', ['string']);
        $resolver->setAllowedTypes('label', ['string', 'null']);
        $resolver->setAllowedTypes('relation_label', ['closure', 'null']);

        $resolver->setDefaults([
            'show_in_frontend' => true,
            'query_behaviour'  => 'sub_query',
            'relation_label'   => null,
            'label'            => null
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
    public function setName(string $name)
    {
        $this->name = $name;
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
    public function setOutputChannelContext(OutputChannelContextInterface $outputChannelContext)
    {
        $this->outputChannelContext = $outputChannelContext;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsFrontendView(): bool
    {
        return $this->options['show_in_frontend'];
    }

    /**
     * {@inheritdoc}
     */
    public function enrichQuery($query)
    {
        if (!$query instanceof Query\Boolean) {
            return $query;
        }

        $runtimeOptions = $this->outputChannelContext->getRuntimeOptions();
        foreach ($runtimeOptions['request_query_vars'] as $key => $value) {
            if (substr($key, 0, strlen($this->options['identifier'])) !== $this->options['identifier']) {
                continue;
            }

            $filterTerm = new Query\MultiTerm();
            $filterTerm->addTerm(new Lucene\Index\Term($this->options['value'], $key));
            $query->addSubquery($filterTerm, true);
        }

        return $query;
    }

    /**
     * {@inheritdoc}
     */
    public function findFilterValueInResult($result)
    {
        // not supported for lucene
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function buildViewVars($filterValues, $result, $query)
    {
        $viewVars = [
            'template' => sprintf('%s/relations.html.twig', self::VIEW_TEMPLATE_PATH),
            'label'    => $this->options['label'],
            'values'   => []
        ];

        $indexProviderOptions = $this->outputChannelContext->getIndexProviderOptions();
        $eventData = $this->eventDispatcher->dispatchAction('build_index', [
            'index' => $this->storageBuilder->getLuceneIndex($indexProviderOptions, ConfigurationInterface::INDEX_BASE_STABLE)
        ]);

        /** @var Lucene\SearchIndexInterface $index */
        $index = $eventData->getParameter('index');

        $filterNames = [];
        foreach ($index->getFieldNames() as $fieldName) {
            if (substr($fieldName, 0, strlen($this->options['identifier'])) !== $this->options['identifier']) {
                continue;
            }

            $filterNames[] = $fieldName;
        }

        $values = [];
        if ($this->options['query_behaviour'] === 'main_query') {
            $values = $this->filterInMainQuery($result, $filterNames);
        } elseif ($this->options['query_behaviour'] === 'sub_query') {
            $values = $this->filterInSubQuery($query, $filterNames);
        }

        if (count($values) === 0) {
            return null;
        }

        $viewVars['values'] = $values;

        return $viewVars;
    }

    /**
     * @param array $result
     * @param array $filterNames
     *
     * @return array
     */
    protected function filterInMainQuery($result, $filterNames)
    {
        if (!is_array($result)) {
            return [];
        }

        return $this->buildResultArray($result, $filterNames);
    }

    /**
     * @param Query\Boolean $mainQuery
     * @param array         $filterNames
     *
     * @return array
     * @throws LuceneException
     */
    protected function filterInSubQuery($mainQuery, $filterNames)
    {
        if (!$mainQuery instanceof Query\Boolean) {
            return [];
        }

        $indexProviderOptions = $this->outputChannelContext->getIndexProviderOptions();
        $eventData = $this->eventDispatcher->dispatchAction('build_index', [
            'index' => $this->storageBuilder->getLuceneIndex($indexProviderOptions, ConfigurationInterface::INDEX_BASE_STABLE)
        ]);

        /** @var Lucene\SearchIndexInterface $index */
        $index = $eventData->getParameter('index');

        $filterQuery = new Query\Boolean();
        $filterQuery->addSubquery($mainQuery, true);

        $queries = [];
        foreach ($filterNames as $filterName) {
            $queries[] = sprintf('%s:%s', $filterName, $this->options['value']);
        }

        $userQuery = QueryParser::parse(sprintf('(%s)', join(' OR ', $queries)), 'utf-8');

        $filterQuery->addSubquery($userQuery, true);
        $filterHits = $index->find($filterQuery);

        return $this->buildResultArray($filterHits, $filterNames);
    }

    /**
     * @param array $result
     * @param array $filterNames
     *
     * @return array
     */
    protected function buildResultArray(array $result, array $filterNames)
    {
        $runtimeOptions = $this->outputChannelContext->getRuntimeOptions();
        $queryFields = $runtimeOptions['request_query_vars'];
        $prefix = $runtimeOptions['prefix'];

        $value = $this->options['value'];

        $filterData = [];

        /** @var Lucene\Search\QueryHit $resultDoc */
        foreach ($result as $resultDoc) {
            $document = $resultDoc->getDocument();
            $fields = $document->getFieldNames();

            $allowedFields = array_values(
                array_filter($fields, function ($field) use ($filterNames, $document, $value) {

                    if (!in_array($field, $filterNames)) {
                        return false;
                    }

                    $fieldValue = $document->getField($field)->getUtf8Value();

                    return $fieldValue === $value;
                })
            );

            if (count($allowedFields) === 0) {
                continue;
            }

            foreach ($allowedFields as $fieldName) {

                if (!isset($filterData[$fieldName])) {
                    $filterData[$fieldName] = 1;
                    continue;
                }

                $filterData[$fieldName]++;
            }
        }

        if (count($filterData) === 0) {
            return [];
        }

        $values = [];
        foreach ($filterData as $fieldName => $fieldCount) {

            $relationLabel = null;
            if ($this->options['relation_label'] !== null) {
                $relationLabel = call_user_func($this->options['relation_label'], $fieldName);
            } else {
                $relationLabel = $fieldName;
            }

            $active = false;
            if (isset($queryFields[$fieldName]) && $queryFields[$fieldName] === $this->options['value']) {
                $active = true;
            }

            $values[] = [
                'name'           => $fieldName,
                'form_name'      => $prefix !== null ? sprintf('%s[%s]', $prefix, $fieldName) : $fieldName,
                'value'          => $this->options['value'],
                'count'          => $fieldCount,
                'active'         => $active,
                'relation_label' => $relationLabel
            ];
        }

        return $values;
    }
}
