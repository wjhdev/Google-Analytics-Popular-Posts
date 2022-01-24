import { Component, h } from '@stencil/core';
import '@webpress/core';
@Component({
  tag: 'analytics-bridge',
  styleUrl: 'analytics-bridge.css',
  shadow: true,
})
export class AnalyticsBridge {
  render() {
    return <h1>Analytics Bridge</h1>;
  }
}
