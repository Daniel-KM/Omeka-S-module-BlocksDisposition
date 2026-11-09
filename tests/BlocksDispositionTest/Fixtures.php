<?php declare(strict_types=1);

/**
 * Listeners of fake modules, named by their first namespace.
 */

namespace FixtureA {
    class Module
    {
        public function first(): void
        {
            echo 'A1';
        }

        public function second(): void
        {
            echo 'A2';
        }
    }
}

namespace FixtureB {
    class Module
    {
        public function show(): void
        {
            echo 'B';
        }
    }
}

namespace FixtureC {
    class Module
    {
        public function show(): void
        {
            echo 'C';
        }
    }
}

namespace FixtureHead {
    class Module
    {
        public function head(): void
        {
        }
    }
}
