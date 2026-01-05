<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription System Help</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .timeline-item { position: relative; padding-left: 2rem; }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: 0.5rem;
            top: 0;
            bottom: -1.5rem;
            width: 2px;
            background: #e5e7eb;
        }
        .timeline-item:last-child::before { display: none; }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen py-12">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">📚 Subscription Management System Help</h1>
                        <p class="text-gray-600 mt-2">Complete guide to automatic suspension and reactivation</p>
                    </div>
                    <a href="{{ url()->previous() }}" class="text-blue-600 hover:text-blue-800 font-medium">
                        ← Back
                    </a>
                </div>
            </div>

            <!-- Timeline Section -->
            <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                <h2 class="text-2xl font-bold text-gray-900 mb-6">🗓️ Event Timeline</h2>

                <div class="space-y-8">
                    <!-- Day 0 -->
                    <div class="timeline-item">
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <div class="h-8 w-8 rounded-full bg-blue-100 flex items-center justify-center">
                                    <span class="text-blue-600 font-bold text-sm">0</span>
                                </div>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-semibold text-gray-900">Day 0: Invoice Generated</h3>
                                <p class="text-gray-600 mt-1">A new invoice is created for the subscription</p>
                                <div class="mt-2 bg-gray-50 rounded p-3">
                                    <p class="text-sm text-gray-700">• Invoice status: <code class="bg-gray-200 px-2 py-1 rounded">open</code></p>
                                    <p class="text-sm text-gray-700">• Due date: Day 10</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Day 10 -->
                    <div class="timeline-item">
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <div class="h-8 w-8 rounded-full bg-yellow-100 flex items-center justify-center">
                                    <span class="text-yellow-600 font-bold text-sm">10</span>
                                </div>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-semibold text-gray-900">Day 10: Invoice Due Date</h3>
                                <p class="text-gray-600 mt-1">Invoice payment is now due</p>
                                <div class="mt-2 bg-yellow-50 rounded p-3">
                                    <p class="text-sm text-gray-700">⚠️ If unpaid, invoice becomes <strong>overdue</strong></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Day 30 -->
                    <div class="timeline-item">
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <div class="h-8 w-8 rounded-full bg-orange-100 flex items-center justify-center">
                                    <span class="text-orange-600 font-bold text-sm">30</span>
                                </div>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-semibold text-gray-900">Day 30: Second Invoice Generated</h3>
                                <p class="text-gray-600 mt-1">Monthly billing cycle generates next invoice</p>
                                <div class="mt-2 bg-orange-50 rounded p-3 border border-orange-200">
                                    <p class="text-sm font-semibold text-orange-800">⚠️ CRITICAL: Customer now has 2 UNPAID INVOICES</p>
                                    <p class="text-sm text-gray-700 mt-1">This triggers the warning system</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Day 40 -->
                    <div class="timeline-item">
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <div class="h-8 w-8 rounded-full bg-red-100 flex items-center justify-center">
                                    <span class="text-red-600 font-bold text-sm">40</span>
                                </div>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-semibold text-gray-900">Day 40: 🚨 5-Day Warning</h3>
                                <p class="text-gray-600 mt-1">First suspension warning email sent</p>
                                <div class="mt-2 bg-red-50 rounded p-3 border border-red-200">
                                    <p class="text-sm font-semibold text-red-800">📧 Email: "Your service will be suspended in 5 days"</p>
                                    <p class="text-sm text-gray-700 mt-2"><strong>Calculation:</strong> 40 days from oldest invoice = 30 days past due</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Day 43 -->
                    <div class="timeline-item">
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <div class="h-8 w-8 rounded-full bg-red-200 flex items-center justify-center">
                                    <span class="text-red-700 font-bold text-sm">43</span>
                                </div>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-semibold text-gray-900">Day 43: 🚨 2-Day Warning</h3>
                                <p class="text-gray-600 mt-1">Final suspension warning email sent</p>
                                <div class="mt-2 bg-red-50 rounded p-3 border border-red-300">
                                    <p class="text-sm font-semibold text-red-900">📧 Email: "Your service will be suspended in 2 days"</p>
                                    <p class="text-sm text-gray-700 mt-2"><strong>Calculation:</strong> 43 days from oldest invoice = 33 days past due</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Day 45 -->
                    <div class="timeline-item">
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <div class="h-8 w-8 rounded-full bg-red-600 flex items-center justify-center">
                                    <span class="text-white font-bold text-sm">45</span>
                                </div>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-semibold text-red-900">Day 45: ⛔ Automatic Suspension</h3>
                                <p class="text-gray-600 mt-1">Service is automatically suspended</p>
                                <div class="mt-2 bg-red-100 rounded p-3 border-2 border-red-400">
                                    <p class="text-sm font-bold text-red-900 mb-2">⛔ SUSPENSION EXECUTED:</p>
                                    <ul class="text-sm text-gray-800 space-y-1 ml-4">
                                        <li>✓ WHM account suspended</li>
                                        <li>✓ Stripe subscription paused</li>
                                        <li>✓ Database status: <code class="bg-red-200 px-2 py-1 rounded">paused</code></li>
                                        <li>✓ Suspension email sent</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment -->
                    <div class="timeline-item">
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <div class="h-8 w-8 rounded-full bg-green-100 flex items-center justify-center">
                                    <span class="text-green-600 font-bold text-sm">💰</span>
                                </div>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-semibold text-green-900">Customer Pays Invoice</h3>
                                <p class="text-gray-600 mt-1">Payment is processed successfully</p>
                                <div class="mt-2 bg-green-50 rounded p-3 border border-green-200">
                                    <p class="text-sm font-semibold text-green-800 mb-2">✅ AUTOMATIC REACTIVATION TRIGGERED:</p>
                                    <ul class="text-sm text-gray-800 space-y-1 ml-4">
                                        <li>✓ Webhook receives <code class="bg-green-200 px-2 py-1 rounded">invoice.payment_succeeded</code></li>
                                        <li>✓ System counts remaining unpaid invoices</li>
                                        <li>✓ If &lt; 2 unpaid invoices → Reactivate!</li>
                                        <li>✓ WHM account unsuspended</li>
                                        <li>✓ Stripe subscription resumed</li>
                                        <li>✓ Database status: <code class="bg-green-200 px-2 py-1 rounded">active</code></li>
                                        <li>✓ Reactivation email sent</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Webhooks Section -->
            <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                <h2 class="text-2xl font-bold text-gray-900 mb-6">🔔 Webhooks</h2>

                <div class="prose max-w-none">
                    <p class="text-gray-700 mb-4">
                        Webhooks provide <strong>immediate reactivation</strong> when a customer pays their invoice.
                        Stripe sends real-time events to our system.
                    </p>

                    <div class="bg-blue-50 rounded-lg p-4 mb-6 border border-blue-200">
                        <h3 class="text-lg font-semibold text-blue-900 mb-3">📍 Webhook Endpoint</h3>
                        <div class="bg-white rounded-lg p-4 border-2 border-blue-300">
                            <code class="text-blue-900 font-mono text-base break-all">https://gestion.revisionalpha.com/stripe/webhook</code>
                        </div>
                        <div class="mt-3">
                            <a href="https://dashboard.stripe.com/webhooks"
                               target="_blank"
                               class="inline-flex items-center text-blue-600 hover:text-blue-800 font-semibold">
                                🔗 Configure in Stripe Dashboard
                                <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                </svg>
                            </a>
                        </div>
                    </div>

                    <h3 class="text-lg font-semibold text-gray-900 mb-3">Configured Events:</h3>

                    <div class="space-y-4">
                        <div class="border-l-4 border-green-500 pl-4">
                            <h4 class="font-semibold text-gray-900">💰 invoice.payment_succeeded</h4>
                            <p class="text-sm text-gray-600 mt-1">
                                Triggered when a customer successfully pays an invoice.
                                <br><strong>Action:</strong> Checks unpaid invoice count and reactivates if &lt; 2
                            </p>
                        </div>

                        <div class="border-l-4 border-red-500 pl-4">
                            <h4 class="font-semibold text-gray-900">❌ invoice.payment_failed</h4>
                            <p class="text-sm text-gray-600 mt-1">
                                Triggered when an invoice payment fails.
                                <br><strong>Action:</strong> Updates invoice status to "open"
                            </p>
                        </div>

                        <div class="border-l-4 border-blue-500 pl-4">
                            <h4 class="font-semibold text-gray-900">🔄 customer.subscription.updated</h4>
                            <p class="text-sm text-gray-600 mt-1">
                                Triggered when a subscription is modified (including pause/resume).
                                <br><strong>Action:</strong> Detects <code>pause_collection</code> field to identify pauses
                            </p>
                        </div>

                        <div class="border-l-4 border-gray-500 pl-4">
                            <h4 class="font-semibold text-gray-900">🗑️ customer.subscription.deleted</h4>
                            <p class="text-sm text-gray-600 mt-1">
                                Triggered when a subscription is canceled.
                                <br><strong>Action:</strong> Marks subscription as "canceled"
                            </p>
                        </div>
                    </div>

                    <div class="mt-6 bg-yellow-50 rounded-lg p-4 border border-yellow-200">
                        <p class="text-sm text-yellow-800">
                            <strong>⚡ Speed:</strong> Webhooks provide <strong>immediate</strong> reactivation (within seconds of payment).
                        </p>
                    </div>
                </div>
            </div>

            <!-- Webhook Backup Section -->
            <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                <h2 class="text-2xl font-bold text-gray-900 mb-6">🔄 Webhook Backup (Scheduler)</h2>

                <div class="prose max-w-none">
                    <p class="text-gray-700 mb-4">
                        In case the webhook fails or is delayed, a <strong>backup command</strong> runs automatically
                        every 2 hours to check for suspended subscriptions that should be reactivated.
                    </p>

                    <div class="bg-purple-50 rounded-lg p-4 mb-6 border border-purple-200">
                        <h3 class="text-lg font-semibold text-purple-900 mb-2">⏰ Schedule</h3>
                        <p class="text-sm text-purple-800">
                            <strong>Frequency:</strong> Every 2 hours
                            <br><strong>Command:</strong> <code class="bg-white px-2 py-1 rounded">php artisan subscriptions:check-reactivations</code>
                        </p>
                    </div>

                    <h3 class="text-lg font-semibold text-gray-900 mb-3">What It Does:</h3>

                    <ol class="list-decimal ml-6 space-y-2 text-gray-700">
                        <li>Finds all subscriptions with status <code class="bg-gray-200 px-2 py-1 rounded">paused</code></li>
                        <li>Counts unpaid invoices for each suspended subscription</li>
                        <li>If unpaid invoices &lt; 2, triggers reactivation process</li>
                        <li>Reactivates WHM account and resumes Stripe subscription</li>
                        <li>Sends reactivation email to customer</li>
                    </ol>

                    <div class="mt-6 bg-green-50 rounded-lg p-4 border border-green-200">
                        <p class="text-sm text-green-800">
                            <strong>✅ Reliability:</strong> Ensures no subscription is left suspended after payment,
                            even if webhook fails.
                        </p>
                    </div>

                    <div class="mt-4 bg-blue-50 rounded-lg p-4 border border-blue-200">
                        <h4 class="font-semibold text-blue-900 mb-2">Manual Execution</h4>
                        <p class="text-sm text-blue-800 mb-2">You can run the command manually at any time:</p>
                        <div class="bg-gray-900 rounded p-3">
                            <code class="text-green-400 font-mono">php artisan subscriptions:check-reactivations</code>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Reference -->
            <div class="bg-gradient-to-r from-blue-50 to-purple-50 rounded-lg shadow-sm p-6">
                <h2 class="text-2xl font-bold text-gray-900 mb-4">📌 Quick Reference</h2>

                <div class="grid md:grid-cols-2 gap-4">
                    <div class="bg-white rounded p-4 border border-gray-200">
                        <h3 class="font-semibold text-gray-900 mb-3">Testing Commands</h3>
                        <div class="space-y-2">
                            <div class="bg-gray-900 rounded p-2">
                                <code class="text-green-400 text-sm font-mono">php artisan subscription:find {search}</code>
                            </div>
                            <div class="bg-gray-900 rounded p-2">
                                <code class="text-green-400 text-sm font-mono">php artisan subscription:check {id}</code>
                            </div>
                            <div class="bg-gray-900 rounded p-2">
                                <code class="text-green-400 text-sm font-mono">php artisan subscription:force-suspend {id}</code>
                            </div>
                            <div class="bg-gray-900 rounded p-2">
                                <code class="text-green-400 text-sm font-mono">php artisan stripe:test-webhook --check</code>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded p-4 border border-gray-200">
                        <h3 class="font-semibold text-gray-900 mb-3">Monitoring</h3>
                        <div class="space-y-2">
                            <div class="bg-gray-900 rounded p-2">
                                <code class="text-yellow-400 text-sm font-mono">tail -f storage/logs/laravel.log | grep webhook</code>
                            </div>
                            <div class="bg-gray-900 rounded p-2">
                                <code class="text-yellow-400 text-sm font-mono">tail -f storage/logs/laravel.log | grep reactivat</code>
                            </div>
                            <div class="bg-gray-900 rounded p-2">
                                <code class="text-yellow-400 text-sm font-mono">tail -f storage/logs/laravel.log | grep suspend</code>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

