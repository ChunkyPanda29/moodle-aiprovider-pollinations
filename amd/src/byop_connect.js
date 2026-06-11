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

/**
 * AMD module for BYOP device flow connection UI.
 *
 * @package    aiprovider_pollinations
 * @copyright  2026 Krissy Painter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/ajax', 'core/str', 'core/notification'], function(Ajax, Str, Notification) {

    /** @var {number|null} Polling interval ID. */
    var pollInterval = null;

    /**
     * Call a Moodle external function via AJAX.
     *
     * @param {string} method The external function name.
     * @param {Object} [args] Arguments to pass.
     * @return {Promise}
     */
    function callExternal(method, args) {
        var request = {
            methodname: method,
            args: args || {}
        };
        return Ajax.call([request])[0];
    }

    /**
     * Load the required language strings.
     *
     * @return {Promise<Object>}
     */
    function loadStrings() {
        return Str.get_strings([
            {key: 'byop_connect', component: 'aiprovider_pollinations'},
            {key: 'byop_connect_desc', component: 'aiprovider_pollinations'},
            {key: 'byop_connected', component: 'aiprovider_pollinations'},
            {key: 'byop_disconnected', component: 'aiprovider_pollinations'},
            {key: 'byop_disconnect', component: 'aiprovider_pollinations'},
            {key: 'byop_code_instructions', component: 'aiprovider_pollinations'},
            {key: 'byop_polling', component: 'aiprovider_pollinations'},
            {key: 'byop_balance', component: 'aiprovider_pollinations'},
            {key: 'byop_error_init', component: 'aiprovider_pollinations'},
            {key: 'byop_error_poll', component: 'aiprovider_pollinations'},
            {key: 'byop_error_denied', component: 'aiprovider_pollinations'},
            {key: 'byop_success', component: 'aiprovider_pollinations'}
        ]).then(function(strings) {
            return {
                connect: strings[0],
                connectDesc: strings[1],
                connected: strings[2],
                disconnected: strings[3],
                disconnect: strings[4],
                codeInstructions: strings[5],
                polling: strings[6],
                balance: strings[7],
                errorInit: strings[8],
                errorPoll: strings[9],
                errorDenied: strings[10],
                success: strings[11]
            };
        });
    }

    /**
     * Render the connected state.
     *
     * @param {Object} strings Language strings.
     * @param {Object} status Status response from get_status.
     */
    function renderConnected(strings, status) {
        var container = document.getElementById('aiprovider_pollinations_byop_container');
        if (!container) {
            return;
        }

        var username = status.username || '';
        var balanceText = status.balance ? strings.balance.replace('{$a}', status.balance) : '';

        var html = '<div class="alert alert-success">';
        html += '<p><strong>' + strings.connected.replace('{$a}', username) + '</strong></p>';
        if (balanceText) {
            html += '<p>' + balanceText + '</p>';
        }
        html += '<button type="button" class="btn btn-secondary" id="aiprovider_pollinations_disconnect_btn">';
        html += strings.disconnect;
        html += '</button>';
        html += '</div>';

        container.innerHTML = html;

        document.getElementById('aiprovider_pollinations_disconnect_btn').addEventListener('click', function() {
            handleDisconnect();
        });
    }

    /**
     * Render the disconnected state.
     *
     * @param {Object} strings Language strings.
     */
    function renderDisconnected(strings) {
        var container = document.getElementById('aiprovider_pollinations_byop_container');
        if (!container) {
            return;
        }

        var html = '<div class="alert alert-info">';
        html += '<p>' + strings.disconnected + '</p>';
        html += '<p><small>' + strings.connectDesc + '</small></p>';
        html += '<button type="button" class="btn btn-primary" id="aiprovider_pollinations_connect_btn">';
        html += strings.connect;
        html += '</button>';
        html += '</div>';

        container.innerHTML = html;

        document.getElementById('aiprovider_pollinations_connect_btn').addEventListener('click', function() {
            handleConnect(strings);
        });
    }

    /**
     * Render the waiting-for-authorisation state.
     *
     * @param {Object} strings Language strings.
     * @param {string} userCode The user code to display.
     * @param {string} verificationUrl The URL to visit.
     */
    function renderWaiting(strings, userCode, verificationUrl) {
        var container = document.getElementById('aiprovider_pollinations_byop_container');
        if (!container) {
            return;
        }

        var instructions = strings.codeInstructions
            .replace('{$a->url}', verificationUrl)
            .replace('{$a->code}', userCode);

        var html = '<div class="alert alert-warning">';
        html += '<p><strong>' + instructions + '</strong></p>';
        html += '<p class="text-muted"><em>' + strings.polling + '</em></p>';
        html += '</div>';

        container.innerHTML = html;
    }

    /**
     * Render an error state.
     *
     * @param {string} message Error message.
     */
    function renderError(message) {
        var container = document.getElementById('aiprovider_pollinations_byop_container');
        if (!container) {
            return;
        }

        container.innerHTML = '<div class="alert alert-danger"><p>' + message + '</p></div>';

        // Show reconnect button after a moment.
        loadStrings().then(function(strings) {
            setTimeout(function() {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn btn-primary mt-2';
                btn.textContent = strings.connect;
                btn.addEventListener('click', function() {
                    handleConnect(strings);
                });
                container.querySelector('.alert').appendChild(btn);
            }, 1000);
        });
    }

    /**
     * Handle the connect button click.
     *
     * @param {Object} strings Language strings.
     */
    function handleConnect(strings) {
        var container = document.getElementById('aiprovider_pollinations_byop_container');
        container.innerHTML = '<div class="alert alert-info"><p>Starting authorisation...</p></div>';

        callExternal('aiprovider_pollinations_init_device_flow')
            .then(function(response) {
                if (!response.success) {
                    renderError(response.error || strings.errorInit);
                    return;
                }
                renderWaiting(strings, response.user_code, response.verification_url);
                startPolling(strings);
            })
            .catch(function(error) {
                Notification.exception(error);
                renderError(strings.errorInit);
            });
    }

    /**
     * Start polling for the device token.
     *
     * @param {Object} strings Language strings.
     */
    function startPolling(strings) {
        if (pollInterval) {
            clearInterval(pollInterval);
        }

        pollInterval = setInterval(function() {
            callExternal('aiprovider_pollinations_poll_device_token')
                .then(function(response) {
                    if (response.success && response.status === 'authorised') {
                        clearInterval(pollInterval);
                        pollInterval = null;
                        renderConnected(strings, {username: '', balance: ''});
                        // Fetch full status to get username + balance.
                        refreshStatus(strings);
                    } else if (response.status === 'denied') {
                        clearInterval(pollInterval);
                        pollInterval = null;
                        renderError(response.error || strings.errorDenied);
                    } else if (response.status === 'expired' || response.status === 'error') {
                        clearInterval(pollInterval);
                        pollInterval = null;
                        renderError(response.error || strings.errorPoll);
                    }
                    // 'pending' — keep polling.
                })
                .catch(function(error) {
                    clearInterval(pollInterval);
                    pollInterval = null;
                    Notification.exception(error);
                    renderError(strings.errorPoll);
                });
        }, 5000);
    }

    /**
     * Refresh the connection status from the server.
     *
     * @param {Object} strings Language strings.
     */
    function refreshStatus(strings) {
        callExternal('aiprovider_pollinations_get_status')
            .then(function(status) {
                if (status.connected) {
                    renderConnected(strings, status);
                } else {
                    renderDisconnected(strings);
                }
            })
            .catch(function(error) {
                Notification.exception(error);
            });
    }

    /**
     * Handle the disconnect button click.
     */
    function handleDisconnect() {
        callExternal('aiprovider_pollinations_disconnect')
            .then(function() {
                loadStrings().then(function(strings) {
                    renderDisconnected(strings);
                });
            })
            .catch(function(error) {
                Notification.exception(error);
            });
    }

    /**
     * Initialise the BYOP connection UI.
     */
    function init() {
        var container = document.getElementById('aiprovider_pollinations_byop_container');
        if (!container) {
            return;
        }

        loadStrings().then(function(strings) {
            // Check current status.
            callExternal('aiprovider_pollinations_get_status')
                .then(function(status) {
                    if (status.connected) {
                        renderConnected(strings, status);
                    } else {
                        renderDisconnected(strings);
                    }
                })
                .catch(function(error) {
                    Notification.exception(error);
                    renderDisconnected(strings);
                });
        });
    }

    return {
        init: init
    };
});
