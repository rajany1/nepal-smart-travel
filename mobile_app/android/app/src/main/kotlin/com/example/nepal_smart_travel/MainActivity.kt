package com.example.nepal_smart_travel

import android.app.AppOpsManager
import android.content.Context
import android.location.Location
import android.location.LocationManager
import android.os.Build
import android.os.Handler
import android.os.Looper
import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel

/**
 * Mock-location ("GPS spoofing") detection for the Oripori report pipeline.
 *
 * Exposes a single channel method, getMockLocationStatus, returning:
 *   isMockLocation : Boolean?  — OS flag on the newest location fix (null when unknown)
 *   status         : genuine | mock_detected | cannot_determine
 *   reason         : short machine-readable reason
 *
 * Detection strategy (strongest platform-supported signals first):
 *   1. Per-fix mock flag — the OS stamps every location delivered by a mock
 *      provider. API 31+: Location.isMock(). API 23-30:
 *      Location.isFromMockProvider(). This is authoritative for that fix.
 *   2. Mock-provider configuration — best-effort AppOps scan for an app
 *      holding the "android:mock_location" op. If a mock app is configured
 *      the fix is NOT flagged as mock, we refuse to claim "genuine" and
 *      report cannot_determine instead (never falsely certify).
 *
 * The result is EVIDENCE for the backend, never proof: Laravel re-evaluates
 * every client claim server-side.
 */
class MainActivity : FlutterActivity() {
    private val locationIntegrityChannel = "oripori/location_integrity"

    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)

        MethodChannel(flutterEngine.dartExecutor.binaryMessenger, locationIntegrityChannel)
            .setMethodCallHandler { call, result ->
                when (call.method) {
                    "getMockLocationStatus" -> {
                        // Detection reads LocationManager + AppOps: keep it off
                        // the main thread, reply on the main thread as required
                        // by MethodChannel.
                        Thread {
                            val payload = try {
                                detectMockLocation()
                            } catch (t: Throwable) {
                                mapOf(
                                    "isMockLocation" to null,
                                    "status" to "cannot_determine",
                                    "reason" to "detection_error"
                                )
                            }
                            Handler(Looper.getMainLooper()).post {
                                result.success(payload)
                            }
                        }.start()
                    }
                    else -> result.notImplemented()
                }
            }
    }

    private fun detectMockLocation(): Map<String, Any?> {
        val locationManager =
            getSystemService(Context.LOCATION_SERVICE) as LocationManager

        val fix = newestKnownFix(locationManager)
            ?: return mapOf(
                "isMockLocation" to null,
                "status" to "cannot_determine",
                "reason" to "no_location_fix"
            )

        val isMock = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
            fix.isMock
        } else {
            @Suppress("DEPRECATION")
            fix.isFromMockProvider
        }

        if (isMock) {
            return mapOf(
                "isMockLocation" to true,
                "status" to "mock_detected",
                "reason" to "location_flagged_mock"
            )
        }

        // Fix not flagged as mock. If some app still holds the mock-location
        // permission we cannot honestly certify the fix as genuine.
        if (isMockLocationAppConfigured()) {
            return mapOf(
                "isMockLocation" to false,
                "status" to "cannot_determine",
                "reason" to "mock_location_app_configured"
            )
        }

        return mapOf(
            "isMockLocation" to false,
            "status" to "genuine",
            "reason" to "location_flag_not_mock"
        )
    }

    /** Newest fix across GPS/network/passive providers (requires location permission). */
    private fun newestKnownFix(locationManager: LocationManager): Location? {
        var newest: Location? = null
        val providers = listOf(
            LocationManager.GPS_PROVIDER,
            LocationManager.NETWORK_PROVIDER,
            LocationManager.PASSIVE_PROVIDER
        )

        for (provider in providers) {
            val candidate = try {
                @Suppress("MissingPermission")
                locationManager.getLastKnownLocation(provider)
            } catch (se: SecurityException) {
                null
            } catch (iae: IllegalArgumentException) {
                null
            } ?: continue

            if (newest == null || candidate.time > newest.time) {
                newest = candidate
            }
        }

        return newest
    }

    /**
     * Best-effort: is ANY installed app currently allowed to inject mock
     * locations (the "Select mock location app" developer setting)? Uses the
     * AppOps API introduced in M (our minSdk), never throws.
     */
    private fun isMockLocationAppConfigured(): Boolean {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.M) return false

        return try {
            val appOps = getSystemService(Context.APP_OPS_SERVICE) as AppOpsManager
            val pm = packageManager

            @Suppress("DEPRECATION")
            val packages = pm.getInstalledPackages(0)
            packages.any { pkg ->
                val appInfo = pkg.applicationInfo ?: return@any false
                try {
                    appOps.checkOpNoThrow(
                        AppOpsManager.OPSTR_MOCK_LOCATION,
                        appInfo.uid,
                        pkg.packageName
                    ) == AppOpsManager.MODE_ALLOWED
                } catch (t: Throwable) {
                    false
                }
            }
        } catch (t: Throwable) {
            false
        }
    }
}
