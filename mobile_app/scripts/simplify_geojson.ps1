param(
    [string]$AssetsDir = "$PSScriptRoot\..\assets",
    [string]$BackendDir = "$PSScriptRoot\..\..\..\backend\resources\views\admin\partials"
)

function Simplify-Ring {
    param([array]$Ring)

    $count = $Ring.Count
    if ($count -le 100) { return $Ring }

    if ($count -gt 200) {
        $keepEvery = 3
    } else {
        $keepEvery = 2
    }

    $simplified = @()
    for ($i = 0; $i -lt $count; $i++) {
        if ($i -eq 0 -or $i -eq ($count - 1)) {
            $simplified += ,@($Ring[$i][0], $Ring[$i][1])
        } elseif ($i % $keepEvery -eq 0) {
            $simplified += ,@($Ring[$i][0], $Ring[$i][1])
        }
    }
    return $simplified
}

function Simplify-Geometry {
    param($Geometry)

    switch ($Geometry.type) {
        "Polygon" {
            $newRings = @()
            foreach ($ring in $Geometry.coordinates) {
                $newRings += ,(Simplify-Ring -Ring $ring)
            }
            $Geometry.coordinates = $newRings
        }
        "MultiPolygon" {
            $newPolygons = @()
            foreach ($polygon in $Geometry.coordinates) {
                $newRings = @()
                foreach ($ring in $polygon) {
                    $newRings += ,(Simplify-Ring -Ring $ring)
                }
                $newPolygons += ,$newRings
            }
            $Geometry.coordinates = $newPolygons
        }
    }
    return $Geometry
}

$files = @(
    @{ Name = "nepal_adm1.geojson"; TargetMin = 200; TargetMax = 400 },
    @{ Name = "nepal_adm2.geojson"; TargetMin = 600; TargetMax = 1024 },
    @{ Name = "nepal_boundary.geojson"; TargetMin = 0; TargetMax = 200 }
)

foreach ($file in $files) {
    $assetPath = Join-Path $AssetsDir $file.Name
    $backendPath = Join-Path $BackendDir $file.Name

    if (-not (Test-Path $assetPath)) {
        Write-Warning "File not found: $assetPath"
        continue
    }

    $beforeSize = [math]::Round((Get-Item $assetPath).Length / 1KB)

    if ($file.Name -eq "nepal_boundary.geojson" -and $beforeSize -lt $file.TargetMax) {
        Write-Output "$($file.Name): ${beforeSize}KB - already under $($file.TargetMax)KB, skipping"
        continue
    }

    $json = Get-Content $assetPath -Raw | ConvertFrom-Json

    $totalRingsBefore = 0
    $totalRingsAfter = 0
    foreach ($feature in $json.features) {
        $before = ($feature.geometry.coordinates | ForEach-Object {
            if ($_.GetType().Name -eq 'Object[]' -and $_[0].GetType().Name -eq 'Object[]') {
                $_[0].Count
            } else {
                $_.Count
            }
        } | Measure-Object -Sum).Sum
        $totalRingsBefore += $before

        $feature.geometry = Simplify-Geometry -Geometry $feature.geometry

        $after = ($feature.geometry.coordinates | ForEach-Object {
            if ($_.GetType().Name -eq 'Object[]' -and $_[0].GetType().Name -eq 'Object[]') {
                $_[0].Count
            } else {
                $_.Count
            }
        } | Measure-Object -Sum).Sum
        $totalRingsAfter += $after
    }

    $outputJson = $json | ConvertTo-Json -Depth 20 -Compress
    Set-Content -Path $assetPath -Value $outputJson -Encoding UTF8
    if (Test-Path $backendPath) {
        Set-Content -Path $backendPath -Value $outputJson -Encoding UTF8
    }

    $afterSize = [math]::Round((Get-Item $assetPath).Length / 1KB)
    $reduction = [math]::Round(($beforeSize - $afterSize) / $beforeSize * 100)

    Write-Output "$($file.Name): ${beforeSize}KB -> ${afterSize}KB (reduced ${reduction}%) | points: $totalRingsBefore -> $totalRingsAfter"
}
