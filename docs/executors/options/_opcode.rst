
.. _executor_opcode_option_php_config:

**php_config**:
  Type(s): ``array``, Default: ``[]``

  Key value array of ini settings, e.g. ``{"max_execution_time":100}``

.. _executor_opcode_option_safe_parameters:

**safe_parameters**:
  Type(s): ``bool``, Default: ``true``

  INTERNAL: Use process process-safe parameters, this option exists for backwards-compatibility and will be removed in PHPBench 2.0

.. _executor_opcode_option_optimisation_stage:

**optimisation_stage**:
  Type(s): ``string``, Default: ``pre``

  If the count should be `pre` or `post` optimisation

.. _executor_opcode_option_dump_path:

**dump_path**:
  Type(s): ``[null, string]``, Default: ``NULL``

  If specified, dump the opcode debug output to this file on each run