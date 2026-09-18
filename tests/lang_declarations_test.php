<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace enrol_mercadopagocpro;

/**
 * Every declaration in db/ has the language string Moodle expects for it.
 *
 * This exists because two of them did not. `db/caches.php` declared the
 * `ratelimit` and `webhookdedupe` caches with no `cachedef_` strings, and it
 * reached a Marketplace reviewer: none of the eight prechecks catches it.
 * `moodle-plugin-ci validate` checks that certain files and a handful of
 * specific strings exist, not that every db/ declaration has its own.
 *
 * Adding a cache, a capability or a message provider without its string is easy
 * to do and invisible until someone opens the page that should name it. This
 * test is the guard.
 *
 * @package   enrol_mercadopagocpro
 * @copyright 2026 Julio Tentor & Associates <https://juliotentor.com>
 * @author    Julio Tentor <jtentor@juliotentor.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class lang_declarations_test extends \advanced_testcase
{
    /** @var string The component these declarations belong to. */
    private const COMPONENT = 'enrol_mercadopagocpro';

    /**
     * Every cache in db/caches.php has a cachedef_ string.
     *
     * @return void
     */
    public function test_cache_definitions_have_strings(): void {
        $definitions = $this->load_db_declaration('caches.php', 'definitions');
        $this->assertNotEmpty($definitions, 'db/caches.php declared no caches.');

        foreach (array_keys($definitions) as $name) {
            $this->assert_string_exists(
                'cachedef_' . $name,
                "db/caches.php declares the cache '{$name}' but there is no "
                    . "cachedef_{$name} string. Administrators would see it unnamed in "
                    . 'Site administration > Plugins > Caching > Configuration.'
            );
        }
    }

    /**
     * Every capability in db/access.php has its string.
     *
     * @return void
     */
    public function test_capabilities_have_strings(): void {
        $capabilities = $this->load_db_declaration('access.php', 'capabilities');
        $this->assertNotEmpty($capabilities, 'db/access.php declared no capabilities.');

        foreach (array_keys($capabilities) as $capability) {
            // Capability 'enrol/mercadopagocpro:config' is named by the string
            // 'mercadopagocpro:config' in this component's language file.
            $identifier = substr($capability, strpos($capability, '/') + 1);

            $this->assert_string_exists(
                $identifier,
                "db/access.php declares '{$capability}' but there is no {$identifier} "
                    . 'string. The capability would appear unnamed wherever roles are '
                    . 'defined or overridden.'
            );
        }
    }

    /**
     * Every message provider in db/messages.php has its string.
     *
     * @return void
     */
    public function test_message_providers_have_strings(): void {
        $providers = $this->load_db_declaration('messages.php', 'messageproviders');
        $this->assertNotEmpty($providers, 'db/messages.php declared no providers.');

        foreach (array_keys($providers) as $provider) {
            $this->assert_string_exists(
                'messageprovider:' . $provider,
                "db/messages.php declares the provider '{$provider}' but there is no "
                    . "messageprovider:{$provider} string. It would appear unnamed in "
                    . 'the notification preferences.'
            );
        }
    }

    /**
     * Load one declaration array out of a db/ file.
     *
     * These files assign an array and nothing else, so requiring them inside a
     * method scope is safe and repeatable.
     *
     * @param  string $file    File name inside db/, e.g. 'caches.php'.
     * @param  string $varname The variable the file assigns, e.g. 'definitions'.
     * @return array
     */
    private function load_db_declaration(string $file, string $varname): array {
        global $CFG;

        $path = $CFG->dirroot . '/enrol/mercadopagocpro/db/' . $file;
        $this->assertFileExists($path);

        require($path);

        return isset($$varname) && is_array($$varname) ? $$varname : [];
    }

    /**
     * Assert a string is defined for this component, with a message that says
     * what to do about it.
     *
     * @param  string $identifier
     * @param  string $message
     * @return void
     */
    private function assert_string_exists(string $identifier, string $message): void {
        $this->assertTrue(
            get_string_manager()->string_exists($identifier, self::COMPONENT),
            $message
        );
    }
}
