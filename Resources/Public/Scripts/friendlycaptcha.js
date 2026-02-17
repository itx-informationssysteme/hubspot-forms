function isFriendlyCaptchaTokenValid(token) {
	if (!token || typeof token !== 'string') return false

	const errorValues = ['error', 'expired', 'fetching', 'unstarted']
	if (errorValues.some(err => token.toLowerCase().includes(err))) {
		return false
	}

	const tokenRegex = /\S{43,}$/
	const hasCorrectFormat = tokenRegex.test(token)
	const hasEnoughParts = token.split('.').length >= 3

	return hasCorrectFormat && hasEnoughParts
}

function handleCaptchaInput(input) {
	const form = input.closest('form.hubspot-form')
	if (!form) return

	const submitBtn = form.querySelector('button[type="submit"]')

	if (input && input.value && isFriendlyCaptchaTokenValid(input.value)) {
		let token = input.value

		let inputHidden = form.querySelector('input[type="hidden"][name="frc-captcha-solution"]')
		if (!inputHidden) {
			inputHidden = document.createElement('input')
			inputHidden.type = 'hidden'
			inputHidden.name = 'frc-captcha-solution'
			form.appendChild(inputHidden)
		}

		inputHidden.value = token

		if (submitBtn) submitBtn.disabled = true
		form.setAttribute('data-captcha-verified', 'false')

		const basePath = window.location.origin + window.location.pathname.replace(/\/[^\/]*$/, '')
		const eidUrl = basePath + '?eID=frcValidateCaptcha'

		fetch(eidUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: 'token=' + encodeURIComponent(token),
		})
			.then(response => response.json())
			.then(data => {
				if (data && data.success) {
					form.setAttribute('data-captcha-verified', 'true')
					if (submitBtn) submitBtn.disabled = false
				} else {
					form.setAttribute('data-captcha-verified', 'false')
					if (submitBtn) submitBtn.disabled = true
				}
			})
			.catch(() => {
				form.setAttribute('data-captcha-verified', 'false')
				if (submitBtn) submitBtn.disabled = true
			})
	}
}

document.addEventListener('DOMContentLoaded', function () {
	const setupInputListeners = input => {
		if (!input.hasAttribute('data-frc-observed')) {
			input.setAttribute('data-frc-observed', '1')
			input.addEventListener('input', () => handleCaptchaInput(input))
			input.addEventListener('change', () => handleCaptchaInput(input))
			handleCaptchaInput(input)
		}
	}

	document.querySelectorAll('input[name="frc-captcha-solution"]').forEach(setupInputListeners)

	document.addEventListener(
		'submit',
		function (e) {
			const form = e.target.closest('form.hubspot-form')
			if (!form) return

			const isVerified = form.getAttribute('data-captcha-verified') === 'true'

			if (!isVerified) {
				e.preventDefault()
				e.stopImmediatePropagation()
				return false
			}
		},
		true
	)

	const bodyObserver = new MutationObserver(() => {
		document.querySelectorAll('input[name="frc-captcha-solution"]').forEach(setupInputListeners)
	})
	bodyObserver.observe(document.body, { childList: true, subtree: true })
})
