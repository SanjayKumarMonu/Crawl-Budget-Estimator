<?php
/**
 * Plugin Name: LLMic Auditor: Public SaaS Edition
 * Description: Public-facing AI SEO fitness auditor with stark, minimalist design.
 * Version: 1.0.0
 * Author: Cursor AI
 * License: GPL-2.0-or-later
 * Text Domain: llmic-auditor
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Estimate tokens using 1 token ~= 4 characters.
 *
 * @param string $string Input string.
 * @return int Estimated token count.
 */
function llmic_auditor_estimate_tokens($string) {
    $length = function_exists('mb_strlen') ? mb_strlen($string, 'UTF-8') : strlen($string);
    return (int) ceil($length / 4);
}

/**
 * Clean HTML into visible text.
 *
 * @param string $html Raw HTML.
 * @return string Cleaned text.
 */
function llmic_auditor_clean_text($html) {
    $text = wp_strip_all_tags($html, true);
    $text = preg_replace('/\s+/', ' ', $text);
    return trim($text);
}

/**
 * Register front-end assets.
 */
function llmic_auditor_register_assets() {
    $css = '
.llmic-auditor {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", "Apple Color Emoji", "Segoe UI Emoji";
    color: #262626;
    background: #f5f5f7;
    border: 1px solid #262626;
    padding: 24px;
    max-width: 820px;
    margin: 24px auto;
    letter-spacing: -0.01em;
}
.llmic-auditor__form {
    display: flex;
    gap: 12px;
    justify-content: center;
    align-items: center;
    flex-wrap: wrap;
}
.llmic-auditor__input {
    border: 2px solid #262626;
    border-radius: 0;
    padding: 12px 14px;
    min-width: 260px;
    width: 100%;
    max-width: 520px;
    font-size: 15px;
    background: #ffffff;
    color: #262626;
}
.llmic-auditor__button {
    border: 2px solid #262626;
    background: #262626;
    color: #ffffff;
    padding: 12px 20px;
    font-size: 14px;
    letter-spacing: 0.02em;
    text-transform: uppercase;
    cursor: pointer;
    transition: background-color 180ms ease-out, color 180ms ease-out, transform 180ms ease-out;
}
.llmic-auditor__button:hover,
.llmic-auditor__button:focus {
    background: #ffffff;
    color: #262626;
}
.llmic-auditor__button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
}
.llmic-auditor__status {
    margin-top: 14px;
    font-size: 13px;
}
.llmic-auditor__status.is-error {
    border: 1px solid #262626;
    background: #ffffff;
    padding: 10px 12px;
}
.llmic-auditor__results {
    margin-top: 24px;
    background: #ffffff;
    border: 1px solid #262626;
    padding: 20px;
}
.llmic-auditor__score {
    font-size: 40px;
    font-weight: 300;
    line-height: 1;
}
.llmic-auditor__bar {
    margin-top: 10px;
    height: 1px;
    background: #d0d0d0;
    position: relative;
}
.llmic-auditor__bar-fill {
    position: absolute;
    left: 0;
    top: 0;
    height: 1px;
    background: #262626;
}
.llmic-auditor__grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
    margin-top: 20px;
}
.llmic-auditor__metric {
    border: 1px solid #262626;
    padding: 12px;
    background: #f5f5f7;
}
.llmic-auditor__metric-label {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin-bottom: 6px;
}
.llmic-auditor__metric-value {
    font-size: 16px;
    font-weight: 600;
}
.llmic-auditor__snippet {
    margin-top: 18px;
    font-size: 13px;
    line-height: 1.5;
    border: 1px solid #262626;
    background: #f5f5f7;
    padding: 12px;
    white-space: pre-wrap;
}
@media (max-width: 640px) {
    .llmic-auditor__grid {
        grid-template-columns: 1fr;
    }
    .llmic-auditor__score {
        font-size: 32px;
    }
}';

    wp_register_style('llmic-auditor', false, array(), '1.0.0');
    wp_add_inline_style('llmic-auditor', $css);

    $js = '
(function() {
    "use strict";

    function createMetric(label, value) {
        var wrapper = document.createElement("div");
        wrapper.className = "llmic-auditor__metric";

        var title = document.createElement("div");
        title.className = "llmic-auditor__metric-label";
        title.textContent = label;

        var val = document.createElement("div");
        val.className = "llmic-auditor__metric-value";
        val.textContent = value;

        wrapper.appendChild(title);
        wrapper.appendChild(val);
        return wrapper;
    }

    function renderResults(container, data) {
        var results = container.querySelector(".llmic-auditor__results");
        results.innerHTML = "";
        results.hidden = false;

        var score = document.createElement("div");
        score.className = "llmic-auditor__score";
        score.textContent = data.efficiency_score + "/100";

        var bar = document.createElement("div");
        bar.className = "llmic-auditor__bar";
        var fill = document.createElement("div");
        fill.className = "llmic-auditor__bar-fill";
        fill.style.width = data.ratio.toFixed(1) + "%";
        bar.appendChild(fill);

        var grid = document.createElement("div");
        grid.className = "llmic-auditor__grid";
        grid.appendChild(createMetric("Token Cost", data.total_tokens + " tokens"));
        grid.appendChild(createMetric("Content Tokens", data.content_tokens + " tokens"));
        grid.appendChild(createMetric("Wasted Spend", data.wasted_spend + " tokens"));
        grid.appendChild(createMetric("Signal-to-Noise", data.ratio.toFixed(1) + "%"));
        grid.appendChild(createMetric("Bot Latency", data.latency_ms + " ms"));
        grid.appendChild(createMetric("HTML Size", data.html_size + " bytes"));

        var snippet = document.createElement("div");
        snippet.className = "llmic-auditor__snippet";
        snippet.textContent = data.snippet;

        results.appendChild(score);
        results.appendChild(bar);
        results.appendChild(grid);
        results.appendChild(snippet);
    }

    function setStatus(container, message, isError) {
        var status = container.querySelector(".llmic-auditor__status");
        status.textContent = message;
        status.className = "llmic-auditor__status" + (isError ? " is-error" : "");
    }

    function initAuditor(container) {
        var input = container.querySelector(".llmic-auditor__input");
        var button = container.querySelector(".llmic-auditor__button");
        var results = container.querySelector(".llmic-auditor__results");

        function runAudit() {
            var url = input.value.trim();
            if (!url) {
                results.hidden = true;
                setStatus(container, "Please enter a valid URL to audit.", true);
                return;
            }

            button.disabled = true;
            results.hidden = true;
            setStatus(container, "Auditing...", false);

            var body = new URLSearchParams();
            body.append("action", "llmic_auditor_run");
            body.append("nonce", container.getAttribute("data-nonce"));
            body.append("url", url);

            fetch(LLMICAuditor.ajaxUrl, {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"
                },
                body: body.toString()
            })
                .then(function(response) { return response.json(); })
                .then(function(payload) {
                    button.disabled = false;
                    if (!payload || !payload.success) {
                        results.hidden = true;
                        var message = payload && payload.data && payload.data.message ? payload.data.message : "Unable to audit that URL.";
                        setStatus(container, message, true);
                        return;
                    }
                    setStatus(container, "", false);
                    renderResults(container, payload.data);
                })
                .catch(function() {
                    button.disabled = false;
                    results.hidden = true;
                    setStatus(container, "Unable to reach the audit service. Please try again.", true);
                });
        }

        button.addEventListener("click", function(event) {
            event.preventDefault();
            runAudit();
        });

        input.addEventListener("keydown", function(event) {
            if (event.key === "Enter") {
                event.preventDefault();
                runAudit();
            }
        });
    }

    document.addEventListener("DOMContentLoaded", function() {
        var auditors = document.querySelectorAll(".llmic-auditor");
        auditors.forEach(initAuditor);
    });
})();';

    wp_register_script('llmic-auditor', false, array(), '1.0.0', true);
    wp_add_inline_script('llmic-auditor', $js);
    wp_localize_script('llmic-auditor', 'LLMICAuditor', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
    ));
}
add_action('wp_enqueue_scripts', 'llmic_auditor_register_assets');

/**
 * Shortcode output.
 *
 * @return string
 */
function llmic_auditor_shortcode() {
    wp_enqueue_style('llmic-auditor');
    wp_enqueue_script('llmic-auditor');

    $nonce = wp_create_nonce('llmic_auditor_nonce');

    return sprintf(
        '<div class="llmic-auditor" data-nonce="%1$s">
            <div class="llmic-auditor__form">
                <input class="llmic-auditor__input" type="url" placeholder="%2$s" aria-label="%3$s" />
                <button class="llmic-auditor__button" type="button">%4$s</button>
            </div>
            <div class="llmic-auditor__status" aria-live="polite"></div>
            <div class="llmic-auditor__results" hidden></div>
        </div>',
        esc_attr($nonce),
        esc_attr__('https://example.com/your-page', 'llmic-auditor'),
        esc_attr__('Enter a URL to audit', 'llmic-auditor'),
        esc_html__('Check URL', 'llmic-auditor')
    );
}
add_shortcode('llmic_auditor', 'llmic_auditor_shortcode');

/**
 * AJAX handler for the audit.
 */
function llmic_auditor_handle_ajax() {
    check_ajax_referer('llmic_auditor_nonce', 'nonce');

    $url = isset($_POST['url']) ? esc_url_raw(wp_unslash($_POST['url'])) : '';
    if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
        wp_send_json_error(array('message' => __('Please enter a valid URL.', 'llmic-auditor')));
    }

    $parsed = wp_parse_url($url);
    if (empty($parsed['scheme']) || !in_array($parsed['scheme'], array('http', 'https'), true)) {
        wp_send_json_error(array('message' => __('Only http and https URLs are supported.', 'llmic-auditor')));
    }

    $args = array(
        'timeout'     => 12,
        'redirection' => 3,
        'user-agent'  => 'LLMic-Bot/1.0',
    );

    $start = microtime(true);
    $response = wp_remote_get($url, $args);
    $elapsed_ms = (microtime(true) - $start) * 1000;

    if (is_wp_error($response)) {
        wp_send_json_error(array('message' => __('Unable to fetch that URL. Please try another page.', 'llmic-auditor')));
    }

    $status = wp_remote_retrieve_response_code($response);
    if ($status < 200 || $status >= 300) {
        wp_send_json_error(array(
            'message' => sprintf(__('The server returned an error (HTTP %d).', 'llmic-auditor'), (int) $status),
        ));
    }

    $html = wp_remote_retrieve_body($response);
    $html_size = strlen($html);
    $total_tokens = llmic_auditor_estimate_tokens($html);
    $clean_text = llmic_auditor_clean_text($html);
    $content_tokens = llmic_auditor_estimate_tokens($clean_text);
    $wasted_spend = max(0, $total_tokens - $content_tokens);
    $ratio = $total_tokens > 0 ? ($content_tokens / $total_tokens) * 100 : 0;
    $efficiency_score = (int) round($ratio);

    if (function_exists('mb_substr')) {
        $snippet = mb_substr($clean_text, 0, 200, 'UTF-8');
    } else {
        $snippet = substr($clean_text, 0, 200);
    }

    if ($snippet === '') {
        $snippet = __('No readable text detected on this page.', 'llmic-auditor');
    }

    wp_send_json_success(array(
        'efficiency_score' => $efficiency_score,
        'ratio'            => round($ratio, 2),
        'total_tokens'     => $total_tokens,
        'content_tokens'   => $content_tokens,
        'wasted_spend'     => $wasted_spend,
        'latency_ms'       => (int) round($elapsed_ms),
        'html_size'        => $html_size,
        'snippet'          => $snippet,
    ));
}
add_action('wp_ajax_llmic_auditor_run', 'llmic_auditor_handle_ajax');
add_action('wp_ajax_nopriv_llmic_auditor_run', 'llmic_auditor_handle_ajax');
