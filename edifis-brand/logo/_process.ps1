Add-Type -AssemblyName System.Drawing
$ErrorActionPreference = 'Stop'
$dir = Split-Path -Parent $MyInvocation.MyCommand.Path
$out = Join-Path $dir 'generated'
New-Item -ItemType Directory -Force -Path $out | Out-Null

$IF  = [System.Drawing.Imaging.PixelFormat]::Format32bppArgb
$PNG = [System.Drawing.Imaging.ImageFormat]::Png
$RW  = [System.Drawing.Imaging.ImageLockMode]::ReadWrite

function To32([string]$p){
  $src = New-Object System.Drawing.Bitmap($p)
  $b = New-Object System.Drawing.Bitmap($src.Width, $src.Height, $IF)
  $g = [System.Drawing.Graphics]::FromImage($b)
  $g.DrawImage($src, 0, 0, $src.Width, $src.Height)
  $g.Dispose(); $src.Dispose(); $b
}

# Key out light, low-saturation background -> transparent
function KeyBg([System.Drawing.Bitmap]$bmp, [int]$thr, [string]$outPath){
  $rect = New-Object System.Drawing.Rectangle(0,0,$bmp.Width,$bmp.Height)
  $d = $bmp.LockBits($rect, $RW, $IF)
  $n = $d.Stride * $bmp.Height
  $buf = New-Object byte[] $n
  [System.Runtime.InteropServices.Marshal]::Copy($d.Scan0, $buf, 0, $n)
  for($i=0; $i -lt $n; $i+=4){
    $b=$buf[$i]; $g=$buf[$i+1]; $r=$buf[$i+2]
    $lum = 0.299*$r + 0.587*$g + 0.114*$b
    $sat = ([math]::Max($r,[math]::Max($g,$b))) - ([math]::Min($r,[math]::Min($g,$b)))
    if($lum -ge $thr -and $sat -le 26){ $buf[$i+3] = 0 }
  }
  [System.Runtime.InteropServices.Marshal]::Copy($buf, 0, $d.Scan0, $n)
  $bmp.UnlockBits($d)
  $bmp.Save($outPath, $PNG)
  Write-Host ("  keyed -> {0}" -f (Split-Path $outPath -Leaf))
}

# Recolor every opaque pixel to a flat color (keep alpha for smooth edges)
function Recolor([string]$inPath, [string]$outPath, [int]$cr, [int]$cg, [int]$cb){
  $bmp = New-Object System.Drawing.Bitmap($inPath)
  $rect = New-Object System.Drawing.Rectangle(0,0,$bmp.Width,$bmp.Height)
  $d = $bmp.LockBits($rect, $RW, $IF)
  $n = $d.Stride * $bmp.Height
  $buf = New-Object byte[] $n
  [System.Runtime.InteropServices.Marshal]::Copy($d.Scan0, $buf, 0, $n)
  for($i=0; $i -lt $n; $i+=4){
    if($buf[$i+3] -gt 0){ $buf[$i]=$cb; $buf[$i+1]=$cg; $buf[$i+2]=$cr }
  }
  [System.Runtime.InteropServices.Marshal]::Copy($buf, 0, $d.Scan0, $n)
  $bmp.UnlockBits($d)
  $bmp.Save($outPath, $PNG); $bmp.Dispose()
  Write-Host ("  recolor -> {0}" -f (Split-Path $outPath -Leaf))
}

# Fit (aspect-preserve, centered) into a square canvas of given size
function SquareIcon([string]$inPath, [string]$outPath, [int]$size, [double]$pad){
  $src = New-Object System.Drawing.Bitmap($inPath)
  $canvas = New-Object System.Drawing.Bitmap($size, $size, $IF)
  $g = [System.Drawing.Graphics]::FromImage($canvas)
  $g.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
  $g.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::AntiAlias
  $g.PixelOffsetMode = [System.Drawing.Drawing2D.PixelOffsetMode]::HighQuality
  $avail = $size * (1.0 - $pad)
  $scale = [math]::Min($avail / $src.Width, $avail / $src.Height)
  $dw = $src.Width * $scale; $dh = $src.Height * $scale
  $dx = ($size - $dw) / 2; $dy = ($size - $dh) / 2
  $g.DrawImage($src, $dx, $dy, $dw, $dh)
  $g.Dispose(); $src.Dispose()
  $canvas.Save($outPath, $PNG); $canvas.Dispose()
  Write-Host ("  sized {0,4}px -> {1}" -f $size, (Split-Path $outPath -Leaf))
}

# Minimal .ico wrapping a 32x32 PNG (modern browsers read PNG-in-ICO)
function WriteIco([string]$pngPath, [string]$icoPath){
  $png = [System.IO.File]::ReadAllBytes($pngPath)
  $ms = New-Object System.IO.MemoryStream
  $bw = New-Object System.IO.BinaryWriter($ms)
  $bw.Write([uint16]0); $bw.Write([uint16]1); $bw.Write([uint16]1)   # reserved, type=icon, count=1
  $bw.Write([byte]32); $bw.Write([byte]32); $bw.Write([byte]0); $bw.Write([byte]0)
  $bw.Write([uint16]1); $bw.Write([uint16]32)
  $bw.Write([uint32]$png.Length); $bw.Write([uint32]22)            # size, offset
  $bw.Write($png); $bw.Flush()
  [System.IO.File]::WriteAllBytes($icoPath, $ms.ToArray())
  Write-Host ("  ico -> {0}" -f (Split-Path $icoPath -Leaf))
}

Write-Host "== ICON (already transparent — no keying) =="
$icon = To32 (Join-Path $dir 'myedifis-icon.png')
$icon.Save((Join-Path $out 'icon-color.png'), $PNG); Write-Host "  copied -> icon-color.png"
$icon.Dispose()
Recolor (Join-Path $out 'icon-color.png') (Join-Path $out 'icon-white.png') 255 255 255
Recolor (Join-Path $out 'icon-color.png') (Join-Path $out 'icon-mono-navy.png') 15 35 80

Write-Host "== FULL LOCKUP (already transparent — no keying) =="
$full = To32 (Join-Path $dir 'myedifis-full.png')
$full.Save((Join-Path $out 'full-color.png'), $PNG); Write-Host "  copied -> full-color.png"
$full.Dispose()
Recolor (Join-Path $out 'full-color.png') (Join-Path $out 'full-white.png') 255 255 255

Write-Host "== APP ICONS / FAVICON (from icon-color) =="
$pad = 0.14
SquareIcon (Join-Path $out 'icon-color.png') (Join-Path $out 'favicon-16.png')      16  $pad
SquareIcon (Join-Path $out 'icon-color.png') (Join-Path $out 'favicon-32.png')      32  $pad
SquareIcon (Join-Path $out 'icon-color.png') (Join-Path $out 'apple-touch-180.png') 180 $pad
SquareIcon (Join-Path $out 'icon-color.png') (Join-Path $out 'android-192.png')     192 $pad
SquareIcon (Join-Path $out 'icon-color.png') (Join-Path $out 'android-512.png')     512 $pad
WriteIco (Join-Path $out 'favicon-32.png') (Join-Path $out 'favicon.ico')

Write-Host "`nDONE. Files in: $out"
Get-ChildItem $out -File | Select-Object Name, @{n='KB';e={[math]::Round($_.Length/1KB,1)}} | Format-Table -AutoSize
