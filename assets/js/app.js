const yearEl = document.getElementById("year");
if (yearEl) {
  yearEl.textContent = new Date().getFullYear();
}

document.querySelectorAll(".js-contact-form").forEach((form) => {
  form.addEventListener("submit", async (event) => {
    event.preventDefault();

    if (form.dataset.submitting === "1") {
      return;
    }
    form.dataset.submitting = "1";

    const status = form.querySelector(".form-status");
    const submitButton = form.querySelector('button[type="submit"]');
    if (submitButton) {
      submitButton.disabled = true;
    }

    if (status) {
      status.textContent = "Submitting your inquiry...";
    }

    const endpoint = form.getAttribute("data-endpoint") || "/dataforge/api/contact";
    const payload = Object.fromEntries(new FormData(form).entries());
    const timeoutMs = 12000;

    try {
      const abortController = typeof AbortController !== "undefined" ? new AbortController() : null;
      const timeoutId = abortController
        ? window.setTimeout(() => abortController.abort(), timeoutMs)
        : null;

      const response = await fetch(endpoint, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify(payload),
        signal: abortController ? abortController.signal : undefined,
      });

      if (timeoutId !== null) {
        window.clearTimeout(timeoutId);
      }

      const result = await response.json();
      if (!response.ok || !result.ok) {
        throw new Error(result.message || "Unable to submit inquiry right now.");
      }

      if (status) {
        status.textContent = result.message || "Thanks. Your inquiry has been captured.";
      }
      form.reset();
    } catch (error) {
      if (status) {
        if (error instanceof Error && error.name === "AbortError") {
          status.textContent = "Request timed out. Please try again.";
        } else {
          status.textContent = error instanceof Error ? error.message : "Unable to submit inquiry right now.";
        }
      }
    } finally {
      if (submitButton) {
        submitButton.disabled = false;
      }
      form.dataset.submitting = "0";
    }
  });
});
