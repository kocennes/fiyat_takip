<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\ValidationRules\Design;

use Closure;
use App\Services\Template\TemplateService;
use Illuminate\Contracts\Validation\ValidationRule;

class TwigLint implements ValidationRule
{
    /**
     * Twig error pattern => translation key (texts.*). The translated lines keep
     * the :line / :match / :match1 / :match2 placeholders filled in below.
     *
     * @var array<string, string>
     */
    private array $syntaxMessages = [
        '/Unexpected token "end of print statement" \("name" expected\)/' => 'bis_twig_missing_filter_name',
        '/Unexpected token "punctuation" of value "\(" \("end of print statement" expected\)/' => 'bis_twig_unexpected_paren',
        '/Unknown "(\w+)" tag/' => 'bis_twig_unknown_tag',
        '/Unexpected "(\w+)" tag/' => 'bis_twig_unexpected_tag',
        '/Unclosed "(\w+)"/' => 'bis_twig_unclosed_tag',
        '/Unexpected token "end of template"/' => 'bis_twig_unexpected_end',
        '/The block "(\w+)" has already been defined/' => 'bis_twig_block_defined_twice',
        '/Unexpected token "operator" of value "="/' => 'bis_twig_unexpected_equals',
        '/Value for argument "(\w+)" is required for filter "(\w+)"/' => 'bis_twig_filter_argument_required',
        '/Unknown "(\w+)" filter/' => 'bis_twig_unknown_filter',
        '/Unknown "(\w+)" function/' => 'bis_twig_unknown_function',
    ];

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {

        $ts = new TemplateService();
        $twig = $ts->twig;

        $template = preg_replace('/<!--.*?-->/s', '', $value ?? '');

        // Pass 1: syntax check
        try {
            $twig->parse($twig->tokenize(new \Twig\Source($template, '')));
        } catch (\Twig\Error\SyntaxError $e) {
            $fail($this->humanizeSyntaxError($e));
            return;
        }

        // Pass 2: sandbox security policy check
        try {
            $twig->createTemplate($template);
        } catch (\Twig\Sandbox\SecurityNotAllowedFilterError $e) {
            $fail(ctrans('texts.bis_twig_filter_not_allowed', ['name' => $e->getFilterName()]));
            return;
        } catch (\Twig\Sandbox\SecurityNotAllowedTagError $e) {
            $fail(ctrans('texts.bis_twig_tag_not_allowed', ['name' => $e->getTagName()]));
            return;
        } catch (\Twig\Sandbox\SecurityNotAllowedFunctionError $e) {
            $fail(ctrans('texts.bis_twig_function_not_allowed', ['name' => $e->getFunctionName()]));
            return;
        } catch (\Twig\Sandbox\SecurityError $e) {
            $fail($e->getMessage());
            return;
        } catch (\Twig\Error\SyntaxError $e) {
            $fail($this->humanizeSyntaxError($e));
            return;
        }

        // Pass 3: mock render with real data to catch runtime errors
        $design = request()->input('design');

        if (is_array($design) && isset($design['design'])) {
            try {
                /** @var \App\Models\User $user */
                $user = auth()->user();
                /** @var \App\Models\Company $company */
                $company = $user->company();

                (new TemplateService())
                    ->setCompany($company)
                    ->setTemplate($design)
                    ->mock();
            } catch (\Twig\Sandbox\SecurityError $e) {
                $fail($e->getMessage());
            } catch (\Twig\Error\SyntaxError $e) {
                $fail($this->humanizeSyntaxError($e));
            } catch (\Twig\Error\RuntimeError $e) {
                $fail($this->humanizeRuntimeError($e));
            } catch (\Twig\Error\Error $e) {
                $fail(ctrans('texts.bis_twig_template_error', ['line' => (string) $e->getTemplateLine(), 'error' => $e->getRawMessage()]));
            }
        }

    }

    private function humanizeSyntaxError(\Twig\Error\SyntaxError $e): string
    {
        $raw = $e->getRawMessage();
        $line = $e->getTemplateLine();

        foreach ($this->syntaxMessages as $pattern => $message_key) {
            if (preg_match($pattern, $raw, $matches)) {
                $message = ctrans('texts.' . $message_key);
                $message = str_replace(':line', (string) $line, $message);
                if (isset($matches[2])) {
                    $message = str_replace(':match2', $matches[2], $message);
                }
                if (isset($matches[1])) {
                    $message = str_replace(':match1', $matches[1], $message);
                    $message = str_replace(':match', $matches[1], $message);
                }
                return $message;
            }
        }

        return ctrans('texts.bis_twig_syntax_error', ['line' => (string) $line, 'error' => $raw]);
    }

    private function humanizeRuntimeError(\Twig\Error\RuntimeError $e): string
    {
        $raw = $e->getRawMessage();
        $line = $e->getTemplateLine();

        if (preg_match('/expects a sequence\/mapping or "Traversable", got "(\w+)"/', $raw, $matches)) {
            return ctrans('texts.bis_twig_expects_list', ['line' => (string) $line, 'type' => $matches[1]]);
        }

        if (preg_match('/Key "(\w+)" does not exist/', $raw, $matches)) {
            return ctrans('texts.bis_twig_variable_missing', ['line' => (string) $line, 'name' => $matches[1]]);
        }

        return ctrans('texts.bis_twig_template_error', ['line' => (string) $line, 'error' => $raw]);
    }
}
