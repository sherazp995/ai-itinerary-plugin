document.addEventListener("DOMContentLoaded", () => {
    const widget = document.createElement("div");
    widget.classList.add("ai-widget");

    widget.innerHTML = `
        <div class="ai-widget-btn">Travel AI</div>
        <div class="ai-widget-box">
            <h3>AI Travel Planner</h3>
            <input id="ai-destination" placeholder="Enter destination">
            <input id="ai-days" type="number" placeholder="Days">
            <button id="ai-generate">Generate</button>
            <div id="ai-result"></div>
        </div>
    `;

    document.body.appendChild(widget);

    document.querySelector("#ai-generate").onclick = function () {
        let destination = document.querySelector("#ai-destination").value;
        let days = document.querySelector("#ai-days").value;

        let data = new FormData();
        data.append("action", "generate_itinerary");
        data.append("destination", destination);
        data.append("days", days);

        fetch(ajaxurl, { method: "POST", body: data })
            .then(res => res.json())
            .then(res => {
                document.querySelector("#ai-result").innerHTML = res.choices[0].message.content;
            });
    };
});
