Executors
=========

Executors are the classes which perform the work and take the measurements.

The default executor is ``remote``.

.. _executor_remote:

``remote``
----------

Executes you benchmark in a separate process for each iteration.

This benchmark records:

- Time in microseconds
- Memory usage

Options:

.. include:: ./executors/options/_remote.rst

.. _executor_opcode:

``opcode``
----------

As with remote but enables opcache debug mode and counts the number of
opcodes:

This benchmark records:

- Same as `remote`
- The number of opcodes (`result_opcode_count`)

Options:

.. include:: ./executors/options/_opcode.rst

.. _executor_local:

``local``
---------

Executes the benchmark in the same process as PHPBench - only useful if you
have included PHPBench as a dependency in your project and it shares the same
autoloader.

This benchmark records:

- Time in microseconds

Options:

- *none*


.. _executor_debug:

``debug``
---------

The debug executor returns a constant set of results, and is useful for
debugging.

Options:

.. include:: ./executors/options/_debug.rst

.. _executor_xdebug_profile:

``xdebug_profile``
------------------

Execute benchmarks and enable XDebug profiling.

.. note::

    You probably want to use the ``xdebug:profile`` command rather than this
    executor.

Options:

.. include:: ./executors/options/_xdebug_profile.rst
