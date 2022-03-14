jQuery(document).ready(function() {
  jQuery(document.body).on(
    'change',
    `select[name=${addon.formName}]`,
    (e) => {
      jQuery('.price > *').addClass('hidden');
      item = e.target.value;
      itemSlug = item.replaceAll(" ", "-").toLowerCase();
      jQuery(`.price .${itemSlug}`).removeClass('hidden');
    }
  );
});
