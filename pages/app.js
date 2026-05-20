window.onload = function () {

    // On récupère les paramètres dans l'URL
    var urlParams = window.location.search;

    if (urlParams.indexOf('success=created') !== -1) {
        showToast('Événement créé avec succès ! 🎉', 'success');
    }
    if (urlParams.indexOf('success=updated') !== -1) {
        showToast('Événement mis à jour.', 'success');
    }
    if (urlParams.indexOf('success=deleted') !== -1) {
        showToast('Événement supprimé.', 'info');
    }
    if (urlParams.indexOf('success=registered') !== -1) {
        showToast('Compte créé avec succès ! Bienvenue 🎉', 'success');
    }
    if (urlParams.indexOf('error=unauthorized') !== -1) {
        showToast('Accès non autorisé.', 'error');
    }
    if (urlParams.indexOf('error=notfound') !== -1) {
        showToast('Événement introuvable.', 'error');
    }

    var menuToggle = document.querySelector('.menu-toggle');
    var mobileNav  = document.querySelector('.mobile-nav');

    if (menuToggle && mobileNav) {
        menuToggle.addEventListener('click', function () {
            if (mobileNav.classList.contains('open')) {
                mobileNav.classList.remove('open');
            } else {
                mobileNav.classList.add('open');
            }
        });
    }

    var toasts = document.querySelectorAll('.toast');
    var i;
    for (i = 0; i < toasts.length; i++) {
        fermerToastAuto(toasts[i]);
    }

};


function showToast(message, type) {
    var container = document.getElementById('toastContainer');
    if (!container) {
        return;
    }

    var icone = 'ℹ️';
    if (type === 'success') { icone = '✅'; }
    if (type === 'error')   { icone = '❌'; }
    if (type === 'warning') { icone = '⚠️'; }
    if (type === 'info')    { icone = 'ℹ️'; }


    var toast = document.createElement('div');
    toast.className = 'toast ' + type;
    toast.innerHTML = '<span>' + icone + '</span><span>' + message + '</span>';

    container.appendChild(toast);

    fermerToastAuto(toast);
}

function fermerToastAuto(toast) {
    var delai = 3500; // millisecondes
    var debut  = null;

    function attendre(timestamp) {
        if (!debut) { debut = timestamp; }
        var elapsed = timestamp - debut;
        if (elapsed >= delai) {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.4s';
            // Suppression du DOM après la transition
            var debutSuppression = null;
            function supprimerApresTransition(ts) {
                if (!debutSuppression) { debutSuppression = ts; }
                if (ts - debutSuppression >= 400) {
                    if (toast.parentNode) {
                        toast.parentNode.removeChild(toast);
                    }
                } else {
                    requestAnimationFrame(supprimerApresTransition);
                }
            }
            requestAnimationFrame(supprimerApresTransition);
        } else {
            requestAnimationFrame(attendre);
        }
    }
    requestAnimationFrame(attendre);
}