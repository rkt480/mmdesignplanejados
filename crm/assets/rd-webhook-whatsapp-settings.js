(() => {
  const templateSelect = document.querySelector("[data-rd-whatsapp-template]");
  const variablesContainer = document.querySelector("[data-rd-whatsapp-template-variables]");
  const templateCatalog = Array.isArray(window.rdWebhookWhatsappTemplateCatalog)
    ? window.rdWebhookWhatsappTemplateCatalog
    : [];
  const leadFields = window.rdWebhookWhatsappLeadFields && typeof window.rdWebhookWhatsappLeadFields === "object"
    ? window.rdWebhookWhatsappLeadFields
    : {};
  const savedMapping = window.rdWebhookWhatsappVariableMapping && typeof window.rdWebhookWhatsappVariableMapping === "object"
    ? window.rdWebhookWhatsappVariableMapping
    : {};

  if (!templateSelect || !variablesContainer) return;

  function variableDefault(variable) {
    const fieldsByVariable = {
      name: "name",
      nome: "name",
      company: "company",
      empresa: "company",
      segment: "segment",
      segmento: "segment",
      whatsapp: "whatsapp",
      phone: "whatsapp",
      telefone: "whatsapp",
      message: "message",
      mensagem: "message",
      email: "answer:email",
    };

    return fieldsByVariable[String(variable).toLowerCase()] || "";
  }

  function mappingValue(mapping, variable) {
    return mapping[variable] ?? mapping[Number(variable)] ?? "";
  }

  function selectedTemplate() {
    return templateCatalog.find((item) => Number(item.id) === Number(templateSelect.value)) || null;
  }

  function renderVariables(mapping = {}) {
    const template = selectedTemplate();
    variablesContainer.replaceChildren();

    if (!template) {
      const hint = document.createElement("small");
      hint.className = "settings-help";
      hint.textContent = "Escolha um template aprovado para configurar as variáveis.";
      variablesContainer.appendChild(hint);
      return;
    }

    if (!Array.isArray(template.variables) || template.variables.length === 0) {
      const hint = document.createElement("small");
      hint.className = "settings-help";
      hint.textContent = "Este template não usa variáveis.";
      variablesContainer.appendChild(hint);
      return;
    }

    const group = document.createElement("div");
    group.className = "settings-subgroup";
    const heading = document.createElement("strong");
    heading.textContent = "Mapeamento das variáveis";
    group.appendChild(heading);

    template.variables.forEach((variable) => {
      const current = String(mappingValue(mapping, variable) || variableDefault(variable));
      const isKnownField = Object.prototype.hasOwnProperty.call(leadFields, current);
      const isCustomAnswer = current.startsWith("answer:") && !isKnownField;
      const label = document.createElement("label");
      label.textContent = `{{${variable}}} recebe`;

      const select = document.createElement("select");
      select.name = `rd_webhook_whatsapp_mapping[${variable}]`;
      select.dataset.rdWhatsappMapping = "";
      select.appendChild(new Option("Selecione um campo do lead", ""));

      Object.entries(leadFields).forEach(([key, fieldLabel]) => {
        select.appendChild(new Option(String(fieldLabel), key));
      });
      select.appendChild(new Option("Resposta personalizada do formulário…", "__custom_answer__"));
      select.value = isCustomAnswer ? "__custom_answer__" : current;
      label.appendChild(select);

      const customInput = document.createElement("input");
      customInput.type = "text";
      customInput.name = `rd_webhook_whatsapp_custom_mapping[${variable}]`;
      customInput.placeholder = "ID ou nome da resposta (ex.: prazo)";
      customInput.value = isCustomAnswer ? current.slice("answer:".length) : "";
      customInput.autocomplete = "off";
      customInput.hidden = !isCustomAnswer;
      customInput.required = isCustomAnswer;
      customInput.setAttribute("aria-label", `Identificador da resposta personalizada para {{${variable}}}`);
      label.appendChild(customInput);

      select.addEventListener("change", () => {
        const custom = select.value === "__custom_answer__";
        customInput.hidden = !custom;
        customInput.required = custom;
        if (!custom) customInput.value = "";
      });

      group.appendChild(label);
    });

    variablesContainer.appendChild(group);
  }

  templateSelect.addEventListener("change", () => {
    renderVariables({});
  });

  renderVariables(savedMapping);
})();
