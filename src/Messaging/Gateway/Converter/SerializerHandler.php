<?php
declare(strict_types=1);

namespace Ecotone\Messaging\Gateway\Converter;

use Ecotone\Messaging\Conversion\ConversionService;
use Ecotone\Messaging\Conversion\MediaType;
use Ecotone\Messaging\Handler\TypeDescriptor;

class SerializerHandler
{
    const MEDIA_TYPE = "ecotone.serializer.media_type";
    const TARGET_TYPE = "ecotone.serializer.target_type";

    /**
     * @var ConversionService
     */
    private $conversionService;

    public function __construct(ConversionService $conversionService)
    {
        $this->conversionService = $conversionService;
    }

    public function convertFromPHP($data, array $metadata)
    {
        $targetMediaType = MediaType::parseMediaType($metadata[self::MEDIA_TYPE]);

        return $this->conversionService->convert(
            $data,
            TypeDescriptor::createFromVariable($data),
            MediaType::createApplicationXPHP(),
            $targetMediaType->hasTypeParameter() ? $targetMediaType->getTypeParameter() : TypeDescriptor::createAnythingType(),
            $targetMediaType
        );
    }

    public function convertToPHP($data, array $metadata)
    {
        return $this->conversionService->convert(
            $data,
            TypeDescriptor::createFromVariable($data),
            MediaType::parseMediaType($metadata[self::MEDIA_TYPE]),
            TypeDescriptor::create($metadata[self::TARGET_TYPE]),
            MediaType::createApplicationXPHP()
        );
    }
}