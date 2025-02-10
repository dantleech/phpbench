<?php

return [
    'bare' => [
        'generator' => 'bare',
    ],
    'bare-vertical' => [
        'generator' => 'bare',
        'vertical' => true,
    ],
    'aggregate' => [
        'generator' => 'expression',
        'cols' => [
            'benchmark',
            'subject',
            'set',
            'revs',
            'its',
            'mem_peak',
            'mode',
            'rstdev',
            'opcodes',

        ]
    ],
    'bar_chart_time' => [
        'generator' => 'component',
        'components' => [
            [
                'extends' => 'bar_chart_time',
            ]
        ],
    ],
    'default' => [
        'generator' => 'expression',
        'cols' => [
            'iter' => 'first(iteration_index)',
            'benchmark' => 'first(benchmark_name)',
            'subject' => 'first(subject_name)',
            'set' => 'first(variant_name)',
            'revs' => 'first(variant_revs)',
            'mem_peak' => 'first(result_mem_peak) as bytes',
            'time_avg' => 'display_as_time(first(result_time_avg), first(subject_time_unit))',
            'comp_z_value' => 'format("%+.2fσ", first(result_comp_z_value))',
            'comp_deviation' => 'format("%+.2f%%", first(result_comp_deviation))',
            'opcodes' => 'first(result_opcode_count)',
        ],
        'aggregate' => ['benchmark_class', 'subject_name', 'variant_index', 'iteration_index'],
    ],
    'memory' => [
        'generator' => 'expression',
        'aggregate' => ['benchmark_class', 'subject_name', 'variant_index', 'iteration_index'],
        'cols' => [
            'iter' => 'first(iteration_index)',
            'benchmark' => 'first(subject_name)',
            'subject' => 'first(subject_name)',
            'set' => 'first(variant_name)',
            'revs' => 'first(variant_revs)',
            'mem_final' => 'first(result_mem_final) as bytes',
            'mem_real' => 'first(result_mem_real) as bytes',
            'mem_peak' => 'first(result_mem_peak) as bytes',
        ],
    ],
    "overview" => [
        "generator" => "component",
        "title" => "Overview",
        "components" => [
            [
                "title" => "Suites",
                "component" => "table_aggregate",
                "partition" => ["suite_tag"],
                "row" => [
                    "suite" => "first(partition['suite_tag'])",
                    "date" => "first(partition['suite_date']) ~ ' ' ~ first(partition['suite_time'])",
                    "php" => "first(partition?['env_php_version'])",
                    "vcs branch" => "first(partition?['env_vcs_branch'])",
                    "xdebug" => "first(partition?['env_php_xdebug'])",
                    "iterations" => "count(partition['result_time_revs'])",
                    "revs" => "sum(partition['result_time_revs'])",
                    "mode" => "mode(partition['result_time_avg']) as time",
                    "net_time" => "sum(partition['result_time_net']) as time"
                ],
            ],
            [
                "component" => "section",
                "tabbed" => true,
                "tab_labels" => ['Time', 'Memory'],
                "components" => [
                    [
                        "title" => "Average iteration times aggregated by benchmark",
                        "component" => "bar_chart_aggregate",
                        "x_partition" => ["benchmark_name"],
                        "bar_partition" => ["suite_tag"],
                        "y_expr" => "mode(partition['result_time_avg'])",
                        "y_axes_label" => "yValue as time precision 1"
                    ],
                    [
                        "title" => "Average peak memory aggregated by benchmark",
                        "component" => "bar_chart_aggregate",
                        "x_partition" => ["benchmark_name"],
                        "bar_partition" => ["suite_tag"],
                        "y_expr" => "mode(partition['result_mem_peak'])",
                        "y_axes_label" => "yValue as memory precision 1"
                    ],
                    [
                        "title" => "By Benchmark",
                        "component" => "bar_chart_aggregate",
                        "x_partition" => ["suite_tag"],
                        "bar_partition" => ["benchmark_name"],
                        "y_expr" => "mode(partition['result_time_avg'])",
                        "y_axes_label" => "yValue as time precision 1"
                    ],
                    [
                        "component" => "section",
                        "title" => "Table",
                        "partition" => ["suite_tag"],
                        "components" => [
                            [
                                "component" => "table_aggregate",
                                "title" => "{{ first(frame.suite_tag) }}",
                                "partition" => ["benchmark_name", "subject_name", "variant_name"],
                                "row" => [
                                    "benchmark" => "first(partition['benchmark_name'])",
                                    "subject" => "first(partition['subject_name']) ~ ' (' ~ first(partition['variant_name']) ~ ')'",
                                    "memory" => "first(partition['result_mem_peak']) as memory",
                                    "min" => "min(partition['result_time_avg']) as time",
                                    "max" => "max(partition['result_time_avg']) as time",
                                    "mode" => "mode(partition['result_time_avg']) as time",
                                    "rstdev" => "rstdev(partition['result_time_avg'])",
                                    "stdev" => "stdev(partition['result_time_avg']) as time"
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ]
    ],
    'benchmark' => [
        "generator" => "component",
        "partition" => ["benchmark_name"],
        "components" => [
            [
                "component" => "section",
                "title" => "{{ first(frame['benchmark_name']) }}",
                "components" => [
                    [
                        "component" => "section",
                        "tabbed" => true,
                        "tab_labels" => ["Time", "Memory"],
                        "components" => [
                            [
                                "title" => "Average iteration times by variant",
                                "component" => "bar_chart_aggregate",
                                "x_partition" => ["subject_name", "variant_name"],
                                "bar_partition" => ["suite_tag"],
                                "y_expr" => "mode(partition['result_time_avg'])",
                                "y_error_margin" => "stdev(partition['result_time_avg'])",
                                "y_axes_label" => "yValue as time precision 1"
                            ],
                            [
                                "title" => "Memory by variant",
                                "component" => "bar_chart_aggregate",
                                "x_partition" => ["subject_name", "variant_name"],
                                "bar_partition" => ["suite_tag"],
                                "y_expr" => "mode(partition['result_mem_peak'])",
                                "y_error_margin" => "stdev(partition['result_mem_peak'])",
                                "y_axes_label" => "yValue as memory precision 1"
                            ]
                        ]
                    ],
                    [
                        "component" => "section",
                        "partition" => ["suite_tag"],
                        "tabbed" => true,
                        "components" => [
                            [
                                "component" => "table_aggregate",
                                "title" => "{{ first(frame.suite_tag) }}",
                                "partition" => ["subject_name", "variant_name"],
                                "row" => [
                                    "subject" => "first(partition['subject_name']) ~ ' (' ~ first(partition['variant_name']) ~ ')'",
                                    "memory" => "first(partition['result_mem_peak']) as memory",
                                    "mode" => "mode(partition['result_time_avg']) as time",
                                    "rstdev" => "rstdev(partition['result_time_avg'])",
                                    "stdev" => "stdev(partition['result_time_avg']) as time"
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ]
    ],
    'benchmark_compare' => [
        "generator" => "component",
        "partition" => ["benchmark_name"],
        "components" => [
            [
                "component" => "section",
                "title" => "{{ first(frame['benchmark_name']) }}",
                "components" => [
                    [
                        "component" => "section",
                        "tabbed" => true,
                        "tab_labels" => ["Time", "Memory"],
                        "components" => [
                            [
                                "title" => "Average iteration times by variant",
                                "component" => "bar_chart_aggregate",
                                "x_partition" => ["subject_name", "variant_name"],
                                "bar_partition" => ["suite_tag"],
                                "y_expr" => "mode(partition['result_time_avg'])",
                                "y_error_margin" => "stdev(partition['result_time_avg'])",
                                "y_axes_label" => "yValue as time precision 1"
                            ],
                            [
                                "title" => "Memory by variant",
                                "component" => "bar_chart_aggregate",
                                "x_partition" => ["subject_name", "variant_name"],
                                "bar_partition" => ["suite_tag"],
                                "y_expr" => "mode(partition['result_mem_peak'])",
                                "y_error_margin" => "stdev(partition['result_mem_peak'])",
                                "y_axes_label" => "yValue as memory precision 1"
                            ]
                        ]
                    ],
                    [
                        "component" => "table_aggregate",
                        "partition" => ["subject_name", "variant_name"],
                        'groups' => [
                            'time (kde mode)' => [
                                'cols' => ['time'],
                            ],
                            'memory' => [
                                'cols' => ['memory'],
                            ],
                        ],
                        "row" => [
                            "subject" => "first(partition['subject_name']) ~ ' (' ~ first(partition['variant_name']) ~ ')'",
                            "time" => [
                                'type' => 'expand',
                                'partition' => 'suite_tag',
                                'cols' => [
                                    'Tag: {{ key }}' => "mode(partition['result_time_avg']) as time ~ ' (' ~ rstdev(partition['result_time_avg']) ~ ')'",
                                ],
                            ],
                            "memory" => [
                                'type' => 'expand',
                                'partition' => 'suite_tag',
                                'cols' => [
                                    'Tag: {{ key }} ' => "mode(partition['result_mem_peak']) as memory",
                                ],
                            ],
                        ]
                    ],
                ]
            ]
        ]
    ]
];
