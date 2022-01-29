import { Component, h, Prop } from '@stencil/core';
import { Connection } from '@webpress/core';

// This won't work on the dasbhoard until WEBPRESS_STENCIL_NAMESPACE
// can be defined in admin-head

@Component({
  tag: 'ab-dash-widget',
  styleUrl: 'ab-dash-widget.css',
})
export class AnalyticsBridgeDashWidget {
  @Prop() global: Connection.Context;

  render() {
    return (
      <ab-popular-posts
        size={15}
        global={this.global}
        renderPost={(post, weight) => {
          return [<wp-title el="h4" post={post} />, weight];
        }}
      />
    );
  }
}
