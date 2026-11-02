<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<nav class="navbar">
  <a href="index.php" class="brand">
    <span class="brand-title">Morfem</span>
    <span class="brand-sub">IR Engine</span>
  </a>

  <ul class="nav-links">
    <li><a href="index.php" class="<?= $currentPage === 'index.php' ? 'active' : '' ?>">Pencarian</a></li>
    <li><a href="compare.php" class="<?= $currentPage === 'compare.php' ? 'active' : '' ?>">Bandingkan Algoritma</a></li>
    <li><a href="pipeline.php" class="<?= $currentPage === 'pipeline.php' ? 'active' : '' ?>">NLP Pipeline</a></li>
    <li><a href="dokumen.php" class="<?= $currentPage === 'dokumen.php' ? 'active' : '' ?>">Kelola Dokumen & Kata</a></li>
    <li><a href="perhitungan.php" class="<?= $currentPage === 'perhitungan.php' ? 'active' : '' ?>">Perhitungan Matematis</a></li>
  </ul>
</nav>
