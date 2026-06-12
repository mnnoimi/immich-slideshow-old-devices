// Globals
var currentLink, currentImg, nextImg, pauseIcon;
var photos, totalPhotos, duration;
var isPaused = false;
var timeoutId = null;
var screenWidth, screenHeight;
var currentIndex = 0;
var isTransitioning = false;

// Feature config
var kenBurnsEnabled = false;
var cropToScreen = true;
var slideshowAlbumId = '';
var slideshowOrientation = 'all';
var slideshowRandom = false;

// Progress bar
var progressInterval = null;
var progressStartTime = 0;
var progressElapsed = 0;

var KB_EFFECTS = ['kb-zoom-in', 'kb-zoom-out', 'kb-pan-right', 'kb-pan-left'];

// ---- Init ----

function initSlideshow(config) {
    currentLink = document.getElementById('current-link');
    currentImg  = document.getElementById('current-img');
    nextImg     = document.getElementById('next-img');
    pauseIcon   = document.getElementById('pause-icon');

    photos               = config.photos;
    totalPhotos          = photos.length;
    duration             = config.duration * 1000;
    kenBurnsEnabled      = config.kenBurns || false;
    cropToScreen         = config.cropToScreen !== undefined ? config.cropToScreen : true;
    slideshowAlbumId     = config.albumId || '';
    slideshowOrientation = config.orientation || 'all';
    slideshowRandom      = config.random || false;

    updateScreenDimensions();

    if (totalPhotos > 0) {
        currentImg.src = buildProxyUrl(photos[0].id);
        currentImg.className = 'active';
        currentImg.addEventListener('error', function () { this.src = '/assets/apple-icon-180.png'; });

        if (totalPhotos > 1) {
            nextImg.src = buildProxyUrl(photos[1].id);
            nextImg.addEventListener('error', function () { this.src = '/assets/apple-icon-180.png'; });
        }

        currentLink.addEventListener('click', togglePause);
        initNavZones();

        if (typeof window.onImageChange === 'function') window.onImageChange(photos[0].id);
        updateCounter();
        startProgress();
        applyKenBurns(currentImg);
        startPeriodicRefresh();
        scheduleNextTransition();
    }
}

// ---- Screen ----

function updateScreenDimensions() {
    screenWidth  = window.innerWidth  || document.documentElement.clientWidth  || document.body.clientWidth;
    screenHeight = window.innerHeight || document.documentElement.clientHeight || document.body.clientHeight;
}

function buildProxyUrl(assetId) {
    return '/proxy.php?asset=' + encodeURIComponent(assetId) +
           '&width='  + encodeURIComponent(screenWidth) +
           '&height=' + encodeURIComponent(screenHeight) +
           '&crop='   + (cropToScreen ? 'true' : 'false');
}

// ---- Ken Burns ----

function applyKenBurns(imgEl) {
    if (!kenBurnsEnabled || !imgEl) return;
    clearKenBurns(imgEl);
    void imgEl.offsetWidth; // force reflow so animation restarts
    var effect = KB_EFFECTS[currentIndex % KB_EFFECTS.length];
    var dur = (duration / 1000 + 1) + 's';
    imgEl.style.webkitAnimationDuration = dur;
    imgEl.style.animationDuration       = dur;
    imgEl.style.webkitAnimationPlayState = 'running';
    imgEl.style.animationPlayState      = 'running';
    imgEl.classList.add(effect);
}

function clearKenBurns(imgEl) {
    if (!imgEl) return;
    for (var i = 0; i < KB_EFFECTS.length; i++) imgEl.classList.remove(KB_EFFECTS[i]);
    imgEl.style.webkitAnimationDuration  = '';
    imgEl.style.animationDuration        = '';
    imgEl.style.webkitAnimationPlayState = '';
    imgEl.style.animationPlayState       = '';
}

function setKenBurnsPlayState(state) {
    if (!kenBurnsEnabled || !currentImg) return;
    currentImg.style.webkitAnimationPlayState = state;
    currentImg.style.animationPlayState       = state;
}

// ---- Progress Bar ----

function startProgress() {
    stopProgress();
    progressElapsed   = 0;
    progressStartTime = Date.now();
    tickProgress();
}

function tickProgress() {
    progressInterval = setInterval(function () {
        var total = progressElapsed + (Date.now() - progressStartTime);
        var pct   = Math.min(total / duration * 100, 100);
        var fill  = document.getElementById('progress-fill');
        if (fill) fill.style.width = pct + '%';
    }, 50);
}

function pauseProgress() {
    if (progressInterval) { clearInterval(progressInterval); progressInterval = null; }
    progressElapsed += Date.now() - progressStartTime;
}

function resumeProgress() {
    progressElapsed   = 0;
    progressStartTime = Date.now();
    tickProgress();
}

function stopProgress() {
    if (progressInterval) { clearInterval(progressInterval); progressInterval = null; }
    var fill = document.getElementById('progress-fill');
    if (fill) fill.style.width = '0%';
}

// ---- Counter ----

function updateCounter() {
    var el = document.getElementById('photo-counter');
    if (el) el.innerHTML = (currentIndex + 1) + ' / ' + totalPhotos;
}

// ---- Album Refresh ----

function refreshAlbum(callback) {
    if (!slideshowAlbumId) { if (callback) callback(false, null); return; }
    var xhr = new XMLHttpRequest();
    xhr.open('GET',
        '/album-refresh.php?album_id=' + encodeURIComponent(slideshowAlbumId) +
        '&orientation='  + encodeURIComponent(slideshowOrientation) +
        '&random='       + (slideshowRandom ? 'true' : 'false'),
        true);
    xhr.onreadystatechange = function () {
        if (xhr.readyState !== 4) return;
        var newPhotos = null;
        var changed   = false;
        if (xhr.status === 200) {
            try {
                newPhotos = JSON.parse(xhr.responseText);
                if (newPhotos && newPhotos.length > 0) {
                    changed = photosHaveChanged(newPhotos);
                }
            } catch (e) {}
        }
        if (callback) callback(changed, newPhotos);
    };
    xhr.send();
}

function photosHaveChanged(newPhotos) {
    if (newPhotos.length !== photos.length) return true;
    // Compare sorted IDs so random-order reshuffles don't count as changes
    var oldIds = photos.map(function (p) { return p.id; }).sort().join(',');
    var newIds = newPhotos.map(function (p) { return p.id; }).sort().join(',');
    return oldIds !== newIds;
}

function applyAlbumUpdate(newPhotos) {
    if (!newPhotos || !newPhotos.length) return;
    photos      = newPhotos;
    totalPhotos = newPhotos.length;
    if (currentIndex >= totalPhotos) currentIndex = 0;
    updateCounter();
    // Preload next image from the updated list
    if (!isTransitioning) {
        nextImg.src = buildProxyUrl(photos[(currentIndex + 1) % totalPhotos].id);
    }
}

function startPeriodicRefresh() {
    setInterval(function () {
        refreshAlbum(function (changed, newPhotos) {
            if (changed) applyAlbumUpdate(newPhotos);
        });
    }, 5 * 60 * 1000); // every 5 minutes
}

// ---- Navigation Zones (click/touch left & right) ----

function initNavZones() {
    setupZone('nav-prev-zone', 'nav-prev-arrow', previousImage);
    setupZone('nav-next-zone', 'nav-next-arrow', nextImage);
}

function setupZone(zoneId, arrowId, action) {
    var zone  = document.getElementById(zoneId);
    var arrow = document.getElementById(arrowId);
    if (!zone) return;

    zone.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        action();
    });

    zone.addEventListener('touchstart', function (e) {
        e.stopPropagation();
        if (arrow) arrow.classList.add('touch-visible');
    }, false);

    zone.addEventListener('touchend', function (e) {
        e.preventDefault();
        e.stopPropagation();
        action();
        if (arrow) setTimeout(function () { arrow.classList.remove('touch-visible'); }, 600);
    }, false);

    zone.addEventListener('touchcancel', function () {
        if (arrow) arrow.classList.remove('touch-visible');
    }, false);
}

function hideAllNavArrows() {
    setTimeout(function () {
        var prev = document.getElementById('nav-prev-arrow');
        var next = document.getElementById('nav-next-arrow');
        if (prev) prev.classList.remove('touch-visible');
        if (next) next.classList.remove('touch-visible');
    }, 600);
}

// ---- Navigation ----

function previousImage() {
    if (totalPhotos === 0 || isTransitioning) return;
    if (timeoutId) clearTimeout(timeoutId);
    hideAllNavArrows();

    isTransitioning = true;
    currentIndex    = (currentIndex - 1 + totalPhotos) % totalPhotos;

    if (typeof window.onImageChange === 'function') window.onImageChange(photos[currentIndex].id);

    nextImg.src          = buildProxyUrl(photos[currentIndex].id);
    currentImg.className = '';
    nextImg.className    = 'active';
    if (!isPaused) applyKenBurns(nextImg);

    var temp = currentImg; currentImg = nextImg; nextImg = temp;

    updateCounter();
    if (isPaused) stopProgress(); else startProgress();

    setTimeout(function () {
        nextImg.src     = buildProxyUrl(photos[(currentIndex + 1) % totalPhotos].id);
        isTransitioning = false;
        scheduleNextTransition();
    }, 1000);
}

function nextImage() {
    if (totalPhotos === 0 || isTransitioning) return;
    if (timeoutId) clearTimeout(timeoutId);
    hideAllNavArrows();

    isTransitioning = true;
    currentIndex    = (currentIndex + 1) % totalPhotos;

    // End of cycle: refresh album and apply changes in-place
    if (currentIndex === 0) {
        refreshAlbum(function (changed, newPhotos) { if (changed) applyAlbumUpdate(newPhotos); });
    }

    if (typeof window.onImageChange === 'function') window.onImageChange(photos[currentIndex].id);

    currentImg.className = '';
    nextImg.className    = 'active';
    if (!isPaused) applyKenBurns(nextImg);

    var temp = currentImg; currentImg = nextImg; nextImg = temp;

    updateCounter();
    if (isPaused) stopProgress(); else startProgress();

    setTimeout(function () {
        nextImg.src     = buildProxyUrl(photos[(currentIndex + 1) % totalPhotos].id);
        isTransitioning = false;
    }, 1000);

    scheduleNextTransition();
}

function scheduleNextTransition() {
    if (!isPaused) timeoutId = setTimeout(nextImage, duration);
}

function togglePause(e) {
    if (e && typeof e.preventDefault === 'function') e.preventDefault();
    isPaused = !isPaused;

    if (isPaused) {
        pauseIcon.classList.add('visible');
        if (timeoutId) clearTimeout(timeoutId);
        pauseProgress();
        setKenBurnsPlayState('paused');
    } else {
        pauseIcon.classList.remove('visible');
        resumeProgress();
        applyKenBurns(currentImg);
        scheduleNextTransition();
    }
}

window.addEventListener('resize', updateScreenDimensions);
window.nextImage     = nextImage;
window.previousImage = previousImage;
window.togglePause   = togglePause;
