<?php

namespace PhpBench\Report\Generator;

use Generator;
use PhpBench\Compat\SymfonyOptionsResolverCompat;
use PhpBench\Data\DataFrame;
use PhpBench\Data\DataFrames;
use PhpBench\Data\Row;
use PhpBench\Expression\Ast\Node;
use PhpBench\Expression\Ast\StringNode;
use PhpBench\Expression\Exception\EvaluationError;
use PhpBench\Expression\Exception\VariableNotFound;
use PhpBench\Expression\ExpressionEvaluator;
use PhpBench\Model\SuiteCollection;
use PhpBench\Registry\Config;
use PhpBench\Report\GeneratorInterface;
use PhpBench\Report\Model\Report;
use PhpBench\Report\Model\Reports;
use PhpBench\Report\Model\Table;
use PhpBench\Report\Transform\SuiteCollectionTransformer;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function array_key_exists;
use function iterator_to_array;

class ExpressionGenerator implements GeneratorInterface
{
    final public const PARAM_TITLE = 'title';
    final public const PARAM_DESCRIPTION = 'description';
    final public const PARAM_COLS = 'cols';
    final public const PARAM_EXPRESSIONS = 'expressions';
    final public const PARAM_BASELINE_EXPRESSIONS = 'baseline_expressions';
    final public const PARAM_AGGREGATE = 'aggregate';
    final public const PARAM_BREAK = 'break';
    final public const PARAM_INCLUDE_BASELINE = 'include_baseline';
    final public const PARAM_HIDE_COLS_WITH_MISSING_VARS = 'hide_cols_with_missing_vars';

    public function __construct(private readonly ExpressionEvaluator $evaluator, private readonly SuiteCollectionTransformer $transformer, private readonly LoggerInterface $logger)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function configure(OptionsResolver $options): void
    {
        $formatTime = function (string $expr) {
            return sprintf(<<<'EOT'
display_as_time(
    %s, 
    coalesce(
        first(subject_time_unit),
        "time"
    ), 
    first(subject_time_precision), 
    first(subject_time_mode))
EOT
                , $expr);
        };

        $options->setDefaults([
            self::PARAM_TITLE => null,
            self::PARAM_DESCRIPTION => null,
            self::PARAM_COLS => null,
            self::PARAM_EXPRESSIONS => [],
            self::PARAM_BASELINE_EXPRESSIONS => [],
            self::PARAM_AGGREGATE => ['suite_tag', 'benchmark_class', 'subject_name', 'variant_index'],
            self::PARAM_BREAK => [],
            self::PARAM_INCLUDE_BASELINE => false,
            self::PARAM_HIDE_COLS_WITH_MISSING_VARS => true,
        ]);

        $options->setAllowedTypes(self::PARAM_TITLE, ['null', 'string']);
        $options->setAllowedTypes(self::PARAM_HIDE_COLS_WITH_MISSING_VARS, ['bool']);
        $options->setAllowedTypes(self::PARAM_DESCRIPTION, ['null', 'string']);
        $options->setAllowedTypes(self::PARAM_COLS, ['array', 'null']);
        $options->setAllowedTypes(self::PARAM_EXPRESSIONS, 'array');
        $options->setAllowedTypes(self::PARAM_BASELINE_EXPRESSIONS, 'array');
        $options->setAllowedTypes(self::PARAM_AGGREGATE, 'array');
        $options->setAllowedTypes(self::PARAM_BREAK, 'array');
        $options->setAllowedTypes(self::PARAM_INCLUDE_BASELINE, 'bool');
        $options->setNormalizer(self::PARAM_EXPRESSIONS, function (Options $options, array $expressions) use ($formatTime) {
            return array_merge([
                'tag' => 'first(suite_tag)',
                'benchmark' => 'first(benchmark_name)',
                'subject' => 'first(subject_name)',
                'set' => 'first(variant_name)',
                'revs' => 'first(variant_revs)',
                'its' => 'first(variant_iterations)',
                'mem_peak' => 'max(result_mem_peak) as memory',
                'best' => $formatTime('min(result_time_avg)'),
                'mode' => $formatTime('mode(result_time_avg)'),
                'mean' => $formatTime('mean(result_time_avg)'),
                'worst' => $formatTime('max(result_time_avg)'),
                'stdev' => $formatTime('stdev(result_time_avg)'),
                'rstdev' => 'rstdev(result_time_avg)',
                'opcodes' => 'mean(result_opcode_count) ~ " " ~ format("±%.2fσ", stdev(result_opcode_count))',
            ], $expressions);
        });
        $options->setNormalizer(self::PARAM_BASELINE_EXPRESSIONS, function (Options $options, array $expressions) use ($formatTime) {
            return array_merge([
                'best' => $formatTime('min(result_time_avg)'),
                'worst' => $formatTime('max(result_time_avg)'),
                'mode' => $formatTime('mode(result_time_avg)') . ' ~" "~ percent_diff(mode(baseline_time_avg), mode(result_time_avg), rstdev(result_time_avg))',
                'mem_peak' => '(first(baseline_mem_peak) as memory) ~ " " ~ percent_diff(first(baseline_mem_peak), first(result_mem_peak))',
                'rstdev' => 'rstdev(result_time_avg) ~ " " ~ percent_diff(rstdev(baseline_time_avg), rstdev(result_time_avg))',
            ], $expressions);
        });
        $options->setNormalizer(self::PARAM_COLS, function (Options $options, ?array $cols) {
            if (null !== $cols) {
                return $cols;
            }

            /** @var array<string, string> $expressions */
            $expressions = $options[self::PARAM_EXPRESSIONS];

            return array_keys($expressions);
        });
        SymfonyOptionsResolverCompat::setInfos($options, [
            self::PARAM_TITLE => 'Title to use for report',
            self::PARAM_DESCRIPTION => 'Description to use for report',
            self::PARAM_COLS => 'Columns to display',
            self::PARAM_EXPRESSIONS => 'Map from column names to expressions',
            self::PARAM_BASELINE_EXPRESSIONS => 'When the baseline is used, expressions here will be merged with the ``expressions``.',
            self::PARAM_AGGREGATE => 'Group rows by these columns',
            self::PARAM_BREAK => 'Group tables by these columns',
            self::PARAM_INCLUDE_BASELINE => 'If the baseline should be included as additional rows, or if it should be inlined',
            self::PARAM_HIDE_COLS_WITH_MISSING_VARS => 'Hide columns which would have produced a "variable not found" error',
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function generate(SuiteCollection $collection, Config $config): Reports
    {
        $expressionMap = $this->resolveExpressionMap($config);
        $baselineExpressionMap = $this->resolveBaselineExpressionMap($config, array_keys($expressionMap));

        /** @var bool $includeBaseline */
        $includeBaseline = $config[self::PARAM_INCLUDE_BASELINE];

        // transform the suite into a data frame
        $frame = $this->transformer->suiteToFrame($collection->firstOnly(), $includeBaseline);
        // parition the data frame into data frames grouped by aggregates
        $frames = $frame->partition(function (Row $row) use ($config) {
            /** @var list<string> $aggregate */
            $aggregate = $config[self::PARAM_AGGREGATE];

            return implode('-', array_map(function (string $column) use ($row) {
                return $row->get($column);
            }, $aggregate));
        });

        // evaluate the aggregated values with the expression language - at
        // this point the data frame is coverted to an array with Node
        // instances rather than scalar values
        $table = iterator_to_array($this->evaluate(
            $frame,
            $frames,
            $expressionMap,
            $baselineExpressionMap,
            $config[self::PARAM_HIDE_COLS_WITH_MISSING_VARS]
        ));

        /** @var list<string> $breakCols */
        $breakCols = $config[self::PARAM_BREAK];

        // split the evaluated tables by "break" for display
        $tables = $this->partition($table, $breakCols);

        // convert the array into Table instances for rendering
        return $this->generateReports($tables, $config);
    }

    /**
     * @param array<string, string> $exprMap
     * @param array<string, string> $baselineExprMap
     *
     * @return Generator<int,array<string,Node>>
     */
    private function evaluate(
        DataFrame $allFrame,
        DataFrames $frames,
        array $exprMap,
        array $baselineExprMap,
        bool $hideColsWithMissingVars = false,
    ): Generator {
        foreach ($frames as $frame) {
            assert($frame instanceof DataFrame);
            $evaledRow = [];

            $exprMap = $frame->row(0)->get(SuiteCollectionTransformer::COL_HAS_BASELINE) ? array_merge(
                $exprMap,
                $baselineExprMap
            ) : $exprMap;

            $columnValues = array_merge(
                [
                    'suite' => $allFrame
                ],
                $frame->nonNullColumnValues()
            );

            foreach ($exprMap as $name => $expr) {
                try {
                    $evaledRow[$name] = $this->evaluator->evaluate(
                        $expr,
                        $columnValues
                    );
                } catch (VariableNotFound $notFound) {
                    if ($hideColsWithMissingVars) {
                        unset($exprMap[$name]);

                        continue;
                    }

                    throw $notFound;
                } catch (EvaluationError $e) {
                    $evaledRow[$name] = new StringNode('ERR');
                    $this->logger->warning(sprintf(
                        'Expression error (column "%s"): %s',
                        $name,
                        $e->getMessage(),
                    ));
                }
            }

            yield $evaledRow;
        }
    }

    /**
     * @param array<string,array<int,array<string,Node>>> $tables
     */
    private function generateReports(array $tables, Config $config): Reports
    {
        /** @var string|null $title */
        $title = $config[self::PARAM_TITLE] ?? null;

        /** @var string|null $description */
        $description = $config[self::PARAM_DESCRIPTION] ?? null;

        return Reports::fromReport(Report::fromTables(
            array_map(function (array $table, string $title) {
                return Table::fromRowArray($table, $title);
            }, $tables, array_keys($tables)),
            $title,
            $description
        ));
    }

    /**
     * @param array<array<string,Node>> $table
     * @param string[] $breakCols
     *
     * @return array<string,array<int,array<string,Node>>>
     */
    private function partition(array $table, array $breakCols): array
    {
        $partitioned = [];

        foreach ($table as $key => $row) {
            $hash = implode('-', array_map(function (string $key) use ($row) {
                if (!array_key_exists($key, $row)) {
                    throw new RuntimeException(sprintf(
                        'Cannot partition table: column "%s" does not exist, known columns: "%s"',
                        $key,
                        implode('", "', array_keys($row))
                    ));
                }

                $value = $row[$key];

                if (!$value instanceof StringNode) {
                    throw new RuntimeException(sprintf(
                        'Partition value for "%s" must be a string, got "%s"',
                        $key,
                        $value::class
                    ));
                }

                return $value->value();
            }, $breakCols));

            foreach ($breakCols as $col) {
                unset($row[$col]);
            }

            if (!isset($partitioned[$hash])) {
                $partitioned[$hash] = [];
            }

            $partitioned[$hash][] = $row;
        }

        return $partitioned;
    }

    /**
     * @return array<string,string>
     */
    private function resolveExpressionMap(Config $config): array
    {
        /** @var array<string, string> $expressions */
        $expressions = $config[self::PARAM_EXPRESSIONS];
        $map = [];
        /** @var array<array-key, string|null> $cols */
        $cols = $config[self::PARAM_COLS];

        foreach ($cols as $key => $expr) {
            if (is_int($key) || null === $expr) {
                $expr ??= $key;

                if (!isset($expressions[$expr])) {
                    throw new RuntimeException(sprintf(
                        'No expression with name "%s" is available, available expressions: "%s"',
                        $expr,
                        implode('", "', array_keys($expressions))
                    ));
                }
                $map[(string)$expr] = $expressions[$expr];

                continue;
            }
            $map[$key] = $expr;
        }

        return $map;
    }

    /**
     * @param string[] $visibleCols
     *
     * @return array<string,string>
     */
    private function resolveBaselineExpressionMap(Config $config, array $visibleCols): array
    {
        $map = [];

        /** @var array<string, string> $baselineExpressions */
        $baselineExpressions = $config[self::PARAM_BASELINE_EXPRESSIONS];

        foreach ($baselineExpressions as $name => $baselineExpression) {
            if (!in_array($name, $visibleCols)) {
                continue;
            }
            $map[$name] = $baselineExpression;
        }

        return $map;
    }
}
