<?php
declare(strict_types=1);

namespace SimplyCodedSoftware\IntegrationMessaging\Handler\Enricher\Converter;

use SimplyCodedSoftware\IntegrationMessaging\Handler\Enricher\DataSetter;
use SimplyCodedSoftware\IntegrationMessaging\Handler\Enricher\EnricherConverter;
use SimplyCodedSoftware\IntegrationMessaging\Handler\Enricher\PropertyPath;
use SimplyCodedSoftware\IntegrationMessaging\Handler\ExpressionEvaluationService;
use SimplyCodedSoftware\IntegrationMessaging\Message;

/**
 * Class ExpressionSetter
 * @package SimplyCodedSoftware\IntegrationMessaging\Handler\Enricher\Converter
 * @author  Dariusz Gafka <dgafka.mail@gmail.com>
 * @internal
 */
class EnrichPayloadWithExpressionConverter implements EnricherConverter
{
    /**
     * @var ExpressionEvaluationService
     */
    private $expressionEvaluationService;
    /**
     * @var PropertyPath
     */
    private $propertyPath;
    /**
     * @var string
     */
    private $expression;
    /**
     * @var DataSetter
     */
    private $dataSetter;

    /**
     * ExpressionSetter constructor.
     *
     * @param ExpressionEvaluationService $expressionEvaluationService
     * @param DataSetter                  $dataSetter
     * @param PropertyPath                $propertyPath
     * @param string                      $expression
     */
    public function __construct(ExpressionEvaluationService $expressionEvaluationService, DataSetter $dataSetter, PropertyPath $propertyPath, string $expression)
    {
        $this->expressionEvaluationService = $expressionEvaluationService;
        $this->propertyPath                = $propertyPath;
        $this->expression                  = $expression;
        $this->dataSetter = $dataSetter;
    }

    /**
     * @inheritDoc
     */
    public function evaluate(Message $enrichMessage, ?Message $replyMessage)
    {
        $dataToEnrich = $this->expressionEvaluationService->evaluate(
            $this->expression, [
            "payload" => $replyMessage ? $replyMessage->getPayload() : null,
            "headers" => $replyMessage ? $replyMessage->getHeaders()->headers() : null,
            "request" => [
                "payload" => $enrichMessage->getPayload(),
                "headers" => $enrichMessage->getHeaders()
            ]
        ]);

        return $this->dataSetter->enrichDataWith($this->propertyPath, $enrichMessage->getPayload(), $dataToEnrich);
    }

    /**
     * @inheritDoc
     */
    public function isPayloadSetter(): bool
    {
        return true;
    }
}