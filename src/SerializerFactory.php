<?php

declare(strict_types=1);

namespace Survos\Mapillary;

use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\NameConverter\MetadataAwareNameConverter;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

final class SerializerFactory
{
    public static function create(): Serializer
    {
        $metadata = new ClassMetadataFactory(new AttributeLoader());

        return new Serializer([
            new ObjectNormalizer($metadata, new MetadataAwareNameConverter($metadata), propertyTypeExtractor: new ReflectionExtractor()),
        ], [new JsonEncoder()]);
    }
}
