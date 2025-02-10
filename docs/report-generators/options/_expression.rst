
.. _generator_expression_option_title:

**title**:
  Type(s): ``[null, string]``, Default: ``NULL``

  Title to use for report

.. _generator_expression_option_description:

**description**:
  Type(s): ``[null, string]``, Default: ``NULL``

  Description to use for report

.. _generator_expression_option_cols:

**cols**:
  Type(s): ``[array, null]``, Default: ``NULL``

  Columns to display

.. _generator_expression_option_expressions:

**expressions**:
  Type(s): ``array``, Default: ``[]``

  Map from column names to expressions

.. _generator_expression_option_baseline_expressions:

**baseline_expressions**:
  Type(s): ``array``, Default: ``[]``

  When the baseline is used, expressions here will be merged with the ``expressions``.

.. _generator_expression_option_aggregate:

**aggregate**:
  Type(s): ``array``, Default: ``[suite_tag, benchmark_class, subject_name, variant_index]``

  Group rows by these columns

.. _generator_expression_option_break:

**break**:
  Type(s): ``array``, Default: ``[]``

  Group tables by these columns

.. _generator_expression_option_include_baseline:

**include_baseline**:
  Type(s): ``bool``, Default: ``false``

  If the baseline should be included as additional rows, or if it should be inlined

.. _generator_expression_option_hide_cols_with_missing_vars:

**hide_cols_with_missing_vars**:
  Type(s): ``bool``, Default: ``true``

  Hide columns which would have produced a "variable not found" error