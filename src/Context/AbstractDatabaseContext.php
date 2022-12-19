<?php

declare(strict_types=1);

namespace Elbformat\SymfonyBehatBundle\Context;

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\TableNode;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Driver\PDO\PgSQL\Driver as PgSqlDriver;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\Mapping\MappingException;
use Doctrine\Persistence\ObjectRepository;
use Symfony\Component\PropertyAccess\PropertyAccessor;

/**
 * @author Hannes Giesenow <hannes.giesenow@elbformat.de>
 *
 * @template T of class-string
 */
abstract class AbstractDatabaseContext implements Context
{
    protected EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    protected function resetDatabase(?string $classOrTableName = null): void
    {
        if (null === $classOrTableName) {
            $classOrTableName = $this->getClassName();
        }
        try {
            $metadata = $this->em->getClassMetadata($classOrTableName);
            $classOrTableName = $metadata->getTableName();
        } catch (MappingException) {
        }

        $this->exec('DELETE FROM '.$classOrTableName);
    }

    protected function resetSequence(?string $className = null): void
    {
        if (null === $className) {
            $className = $this->getClassName();
        }
        $metadata = $this->em->getClassMetadata($className);
        $tableName = $metadata->getTableName();
        if ($this->em->getConnection()->getDriver() instanceof PgSqlDriver) {
            if ($metadata->idGenerator) {
                $seqName = $metadata->sequenceGeneratorDefinition['sequenceName'];
                $this->exec("ALTER SEQUENCE $seqName RESTART WITH 1");
            }
        } else {
            $this->exec('ALTER TABLE '.$tableName.' AUTO_INCREMENT=1');
        }
    }

    protected function createObject(TableNode $table): void
    {
        $obj = $this->newObject();
        $pa = new PropertyAccessor();
        foreach ($table->getRowsHash() as $key => $val) {
            $val = $this->mapTableValue($key, $val);
            $pa->setValue($object, $key, $val);
        }
        $this->em->persist($obj);
        $this->em->flush();
        $this->em->clear();
    }

    protected function createRelation(int $id1, string $class2, $id2, string $relationName): void
    {
        $entity1 = $this->getRepo()->find($id1);
        $entity2 = $this->findEntity($class2, $id2);
        $pa = new PropertyAccessor();
        // We need to do this manually as the PA does not support adder/remover by now.
        $collection = $pa->getValue($entity1, $relationName);
        $collection->add($entity2);
        $this->em->flush();
        $this->em->clear();
    }

    protected function assertObject(TableNode $table, bool $printAlternatives = true): void
    {
        $repo = $this->getRepo();
        $data = $table->getRowsHash();

        // Convert types
        foreach ($data as $key => $val) {
            $data[$key] = $this->convertAssertionValue($val,$this->getTypeOfProperty($key));
        }

        // Found
        if (null !== $repo->findOneBy($data)) {
            return;
        }

        $this->em->clear();

        // Print available entities
        if ($printAlternatives) {
            $this->printAlternatives($data);
        }
        throw new \DomainException('Not found');
    }

    protected function assertNoObject(TableNode $table): void
    {
        try {
            $this->assertObject($table, false);
        } catch (\DomainException) {
            return;
        }
        throw new \DomainException('Found');
    }

    /**
     * @param class-string $containingClass
     */
    protected function assertCollectionContains(int $containerId, string $containingClass, int $containingId, string $relation): void
    {
        $mainEntry = $this->getRepo()->find($containerId);
        $containingEntry = $this->findEntity($containingClass, $containingId);
        $pa = new PropertyAccessor();
        $collection = $pa->getValue($mainEntry, $relation);
        if (!$collection->contains($containingEntry)) {
            throw new \DomainException(sprintf('%s(%d) not found ', $containingClass, $containerId));
        }
    }

    protected function exec(string $query): void
    {
        $this->em->getConnection()->executeQuery($query);
    }

    protected function mapTableValue(string $key, string $value): mixed
    {
        $type = $this->getTypeOfProperty($key);

        switch (true) {
            // @todo how to detect Enums?
            case str_contains($type, 'Enum'):
                return $type::from($value);
            case is_numeric($value) && 'string' !== $type:
                return (int) $value;
            // Reference to another entity with <Entity>::<ID>
            case preg_match('/^(.+)::(.+)$/', $value, $match):
                return $this->em->getRepository('App\\Entity\\'.$match[1])->find($match[2]);
            case 'DateTimeInterface' === $type:
            case 'DateTimeImmutable' === $type:
                return new \DateTimeImmutable($value);
            case 'DateTime' === $type:
                return new \DateTime($value);
            case 'bool' === $type:
                if ('true' === $value) {
                    return true;
                }
                if ('false' === $value) {
                    return false;
                }

                return (bool) $value;
            default:
                return $value;
        }
    }

    protected function convertAssertionValue(string $value, ?string $type): mixed
    {
        if ('NULL' === $value) {
            return null;
        }

        return match ($type) {
            'bool' => 'false' !== $value && '0' !== $value,
            'DateTimeInterface', 'DateTime' => new \DateTime($value),
            'DateTimeImmutable' => new \DateTimeImmutable($value),
            default => $value,
        };
    }

    /** @param array<string,string> $data */
    protected function printAlternatives(array $data): void
    {
        $pa = new PropertyAccessor();
        $refl = new \ReflectionClass($this->newObject());
        printf("%-20s | %-20s | %-20s |\n", 'Field', 'Expected', 'Found');
        foreach ($this->getRepo()->findAll() as $item) {
            printf("%-20s | %-20s | %-20s |\n", str_repeat('-', 20), str_repeat('-', 20), str_repeat('-', 20));
            foreach ($data as $key => $val) {
                $realVal = $pa->getValue($item, $key);
                $type = $refl->getProperty($key)->getType()->getName();
                switch (true) {
                    case 'bool' === $type:
                        $val = $val ? 'true' : 'false';
                        $realVal = $realVal ? 'true' : 'false';
                        break;
                    case $realVal instanceof \DateTimeInterface:
                        $val = $val?->format('c');
                        $realVal = $realVal?->format('c');
                        break;
                    case $realVal instanceof \BackedEnum:
                        $val = $val->value;
                        $realVal = $realVal->value;
                        break;
                    case $realVal instanceof Collection:
                        $realVal = implode(' / ', $realVal->toArray());
                        break;
                }
                printf("%-20s | %20s | %20s |\n", $key, $val, $realVal);
            }
        }
    }

    /** @return T */
    protected function newObject(): object
    {
        $className = $this->getClassName();

        return new $className();
    }

    /** @return EntityRepository<T> */
    protected function getRepo(): ObjectRepository
    {
        return $this->em->getRepository($this->getClassName());
    }

    /**
     * @param class-string<T> $entityName
     *
     * @return T
     */
    protected function findEntity(string $entityName, int $id)
    {
        return $this->em->getRepository($entityName)->find($id);
    }

    protected function getTypeOfProperty(string $propertyName): ?string
    {
        $refl = new \ReflectionClass($this->getClassName());
        if (!$refl->getProperty($propertyName)->hasType()) {
            return null;
        }
        return $refl->getProperty($propertyName)->getType()?->getName();
    }

    /** @return T */
    abstract protected function getClassName(): string;
}
